<?php

namespace App\Notifications;

use App\Enums\BusinessRole;
use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Enums\LeadStatus;
use App\Models\AdminNotification;
use App\Models\DataTable;
use App\Models\DataTableRow;
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
            ->withoutGlobalScopes()
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
