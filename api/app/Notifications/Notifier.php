<?php

namespace App\Notifications;

use App\Enums\BusinessRole;
use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Enums\LeadStatus;
use App\Models\AdminNotification;
use App\Models\Candidate;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\FeedbackMessage;
use App\Models\Lead;
use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends the bell's notifications: to a business's people by what their role
 * opens (BusinessRole), and to platform staff. Sending never breaks what
 * caused it (an order from the site is saved even if this fails).
 */
class Notifier
{
    /** How many of today's orders the "today" notification names. */
    private const TODAY_LISTED = 3;

    /** A new order from the business's site: for everyone who works with orders. */
    public function newLead(Lead $lead): void
    {
        $this->safely(function () use ($lead) {
            $tenant = Tenant::query()->find($lead->tenant_id);
            if (! $tenant) {
                return;
            }

            $this->toBusiness(
                $tenant,
                BusinessRole::ORDERS,
                NotificationType::NewLead,
                'Նոր պատվեր կայքից',
                $this->line([$lead->name, $lead->phone, $lead->service?->trans('name', 'hy') ?? $lead->product?->trans('name', 'hy')]),
                '/admin/orders/site?search='.rawurlencode((string) ($lead->phone ?: $lead->name)),
            );
        });
    }

    /** A message from the site's feedback form: for everyone who works with orders. */
    public function feedback(FeedbackMessage $message): void
    {
        $this->safely(function () use ($message) {
            $tenant = Tenant::query()->find($message->tenant_id);
            if (! $tenant) {
                return;
            }

            $this->toBusiness(
                $tenant,
                BusinessRole::ORDERS,
                NotificationType::Feedback,
                'Նոր հաղորդագրություն կայքից',
                $this->line([$message->name, $message->phone ?: $message->email, Str::limit($message->message, 80)]),
                '/admin/site/feedback',
            );
        });
    }

    /**
     * A message in a business's chat with the platform: the platform team's
     * (or the platform's own, e.g. about the subscription) goes to the
     * business's owner and admins, the business's to platform staff.
     *
     * @param  array{title?: string, url?: string, type?: NotificationType}  $notice
     */
    public function supportMessage(Tenant $tenant, SupportMessage $message, array $notice = []): void
    {
        $this->safely(function () use ($tenant, $message, $notice) {
            if ($message->from_platform) {
                $this->toBusiness(
                    $tenant,
                    BusinessRole::SETTINGS,
                    $notice['type'] ?? NotificationType::SupportMessage,
                    $notice['title'] ?? 'Նոր հաղորդագրություն Phay-ից',
                    Str::limit($message->body, 160),
                    $notice['url'] ?? '/admin/support',
                );

                return;
            }

            $this->send(
                $this->platformStaff(),
                $tenant,
                NotificationScope::Platform,
                NotificationType::SupportMessage,
                'Հաղորդագրություն՝ '.$tenant->name,
                Str::limit($message->body, 160),
                '/admin/platform/support?tenant='.$tenant->id,
            );
        });
    }

    /**
     * About the business's connected Instagram / Facebook (the access ends
     * soon, or Meta stopped accepting it): for the owner and admins, once
     * per $dedupeKey.
     */
    public function social(Tenant $tenant, string $title, string $body, string $dedupeKey): void
    {
        $this->safely(fn () => $this->send(
            $this->businessPeople($tenant, BusinessRole::SETTINGS),
            $tenant,
            NotificationScope::Business,
            NotificationType::Social,
            $title,
            $body,
            '/admin/social',
            Str::limit($dedupeKey, 120, ''),
        ));
    }

    /** The reader opened the chat: its message notifications are read too. */
    public function supportRead(Tenant $tenant, bool $readerIsPlatform): void
    {
        AdminNotification::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', NotificationType::SupportMessage)
            ->where('scope', $readerIsPlatform ? NotificationScope::Platform : NotificationScope::Business)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Today's orders on the calendar (rows of order tables dated today and
     * the site's orders for today), once a day per person, made when they
     * first look at the bell that day: no scheduler needed.
     */
    public function todayOrders(User $user, Tenant $tenant): void
    {
        if ($user->isPlatformStaff()
            || ! $user->businessRole($tenant)?->can(BusinessRole::ORDERS)) {
            return;
        }

        $today = CarbonImmutable::now($tenant->timezone ?: config('app.timezone'))->startOfDay();
        $key = 'today:'.$tenant->id.':'.$today->toDateString();

        if (AdminNotification::query()->where('user_id', $user->id)->where('dedupe_key', $key)->exists()) {
            return;
        }

        $entries = $this->todayEntries($tenant, $today);
        if ($entries->isEmpty()) {
            return;
        }

        $this->safely(fn () => $this->send(
            collect([$user]),
            $tenant,
            NotificationScope::Business,
            NotificationType::TodayOrders,
            'Այսօրվա պատվերներ՝ '.$entries->count(),
            $entries->take(self::TODAY_LISTED)->implode('; ')
                .($entries->count() > self::TODAY_LISTED ? '; …' : ''),
            '/admin/calendar',
            $key,
        ));
    }

    /**
     * The team today, once a day per person, made when they first look at
     * the bell: who is away today, who goes away tomorrow (everyone working
     * with orders plans around it), today's interviews and birthdays (those
     * who manage the team).
     */
    public function teamToday(User $user, Tenant $tenant): void
    {
        $role = $user->businessRole($tenant);
        if ($user->isPlatformStaff() || ! $role?->can(BusinessRole::ORDERS)) {
            return;
        }

        $today = CarbonImmutable::now($tenant->timezone ?: config('app.timezone'))->startOfDay();
        $key = 'team:'.$tenant->id.':'.$today->toDateString();
        if (AdminNotification::query()->where('user_id', $user->id)->where('dedupe_key', $key)->exists()) {
            return;
        }

        $day = $today->toDateString();
        $tomorrow = $today->addDay()->toDateString();
        $absences = EmployeeAbsence::withoutTenancy()->with('employee:id,name')
            ->where('tenant_id', $tenant->id)
            ->overlapping($day, $tomorrow)
            ->orderBy('starts_on')
            ->get();
        $away = $absences->filter(fn (EmployeeAbsence $a) => $a->starts_on->toDateString() <= $day);
        $leaving = $absences->filter(fn (EmployeeAbsence $a) => $a->starts_on->toDateString() === $tomorrow);

        $manager = $role->can(BusinessRole::SETTINGS);
        $interviews = $manager ? Candidate::withoutTenancy()->where('tenant_id', $tenant->id)
            ->where('interview_at', '>=', $day.' 00:00:00')
            ->where('interview_at', '<', $tomorrow.' 00:00:00')
            ->orderBy('interview_at')
            ->get() : collect();
        $birthdays = $manager ? Employee::withoutTenancy()->where('tenant_id', $tenant->id)->where('is_active', true)->whereNotNull('birth_date')->get()
            ->filter(fn (Employee $e) => $e->birth_date->format('m-d') === $today->format('m-d')) : collect();

        $until = fn (EmployeeAbsence $a) => $a->starts_on->eq($a->ends_on) ? '' : ' մինչև '.$a->ends_on->format('d.m');
        $lines = array_filter([
            $away->isNotEmpty() ? 'Այսօր բացակա են՝ '.$away->map(fn (EmployeeAbsence $a) => $a->employee?->name.' ('.mb_strtolower($a->kind->label()).$until($a).')')->implode(', ') : null,
            $leaving->isNotEmpty() ? 'Վաղվանից՝ '.$leaving->map(fn (EmployeeAbsence $a) => $a->employee?->name.' ('.mb_strtolower($a->kind->label()).$until($a).')')->implode(', ') : null,
            $interviews->isNotEmpty() ? 'Հարցազրույցներ՝ '.$interviews->map(fn (Candidate $c) => substr($c->interviewTime(), 11, 5).' '.$c->name)->implode(', ') : null,
            $birthdays->isNotEmpty() ? 'Ծննդյան օր՝ '.$birthdays->pluck('name')->implode(', ') : null,
        ]);
        if ($lines === []) {
            return;
        }

        $title = match (true) {
            $away->isNotEmpty() => 'Այսօր բացակա են՝ '.$away->count(),
            $leaving->isNotEmpty() => 'Վաղը բացակա կլինեն՝ '.$leaving->count(),
            $interviews->isNotEmpty() => 'Այսօրվա հարցազրույցներ՝ '.$interviews->count(),
            default => 'Այսօր ծննդյան օր է',
        };

        $this->safely(fn () => $this->send(
            collect([$user]),
            $tenant,
            NotificationScope::Business,
            NotificationType::Team,
            $title,
            Str::limit(implode('. ', $lines), 600),
            $manager ? '/admin/employees?tab=absences' : '/admin/calendar',
            $key,
        ));
    }

    /** @return Collection<int, string> one short line per order of the day, by time */
    private function todayEntries(Tenant $tenant, CarbonImmutable $today): Collection
    {
        $tables = DataTable::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('section', DataTableSection::Orders)
            ->get()
            ->filter(fn (DataTable $table) => $table->dateColumn() !== null)
            ->keyBy('id');

        $rows = $tables->isEmpty() ? collect() : DataTableRow::query()
            ->withoutGlobalScopes()->counted()
            ->whereIn('data_table_id', $tables->keys())
            ->where('occurred_at', '>=', $today->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $today->addDay()->format('Y-m-d H:i:s'))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'data_table_id', 'data', 'occurred_at']);

        $entries = $rows->map(function (DataTableRow $row) use ($tables) {
            $table = $tables[$row->data_table_id];
            $data = $row->data ?? [];
            $time = $table->dateColumn()->type === DataColumnType::DateTime
                ? CarbonImmutable::parse($row->getRawOriginal('occurred_at'))->format('H:i')
                : null;
            $what = collect([ColumnRole::Customer, ColumnRole::Item, ColumnRole::Address])
                ->map(fn (ColumnRole $role) => $table->roleColumn($role)?->key)
                ->filter()
                ->map(fn (string $key) => $data[$key] ?? null);

            return $this->line([$time, ...$what->all()]) ?? $table->name;
        });

        $leads = Lead::query()
            ->withoutGlobalScopes()
            ->with('service')
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('status', [LeadStatus::Cancelled, LeadStatus::Completed])
            ->where('preferred_date', '>=', $today->toDateString())
            ->where('preferred_date', '<', $today->addDay()->toDateString())
            ->orderBy('id')
            ->get()
            ->map(fn (Lead $lead) => $this->line([$lead->name, $lead->service?->trans('name', 'hy')]) ?? 'Պատվեր կայքից');

        return $entries->concat($leads)->values();
    }

    private function toBusiness(Tenant $tenant, string $permission, NotificationType $type, string $title, ?string $body, ?string $url): void
    {
        $this->send($this->businessPeople($tenant, $permission), $tenant, NotificationScope::Business, $type, $title, $body, $url);
    }

    /** The business's own people whose role opens this (platform staff entering it aren't its people). */
    public function businessPeople(Tenant $tenant, string $permission): Collection
    {
        return $tenant->users()->get()->filter(function (User $user) use ($permission) {
            $role = BusinessRole::tryFrom((string) $user->pivot->role) ?? BusinessRole::Moderator;

            return ! $user->isPlatformStaff() && $role->can($permission);
        })->values();
    }

    /** @return Collection<int, User> */
    public function platformStaff(): Collection
    {
        return User::query()->where('is_super_admin', true)->orWhere('is_platform_moderator', true)->get();
    }

    /** @param Collection<int, User> $users */
    private function send(Collection $users, ?Tenant $tenant, NotificationScope $scope, NotificationType $type, string $title, ?string $body, ?string $url, ?string $dedupeKey = null): void
    {
        foreach ($users as $user) {
            try {
                AdminNotification::query()->create([
                    'user_id' => $user->id,
                    'tenant_id' => $tenant?->id,
                    'scope' => $scope,
                    'type' => $type->value,
                    'title' => Str::limit($title, 250),
                    'body' => $body,
                    'url' => $url,
                    'dedupe_key' => $dedupeKey,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Already sent (two requests at once): once is enough.
            }
        }
    }

    /** @param list<mixed> $parts */
    private function line(array $parts): ?string
    {
        $parts = array_values(array_filter(array_map(
            fn ($part) => is_scalar($part) ? trim((string) $part) : '',
            $parts,
        ), fn (string $part) => $part !== ''));

        return $parts === [] ? null : Str::limit(implode(' · ', $parts), 160);
    }

    private function safely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
