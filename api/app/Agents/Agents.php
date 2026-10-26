<?php

namespace App\Agents;

use App\Models\BrandProfile;
use App\Models\ModulePrice;
use App\Models\Tenant;
use App\Models\TenantAgent;
use App\Models\User;
use App\Notifications\NotificationType;
use App\Services\SupportChat;
use App\Subscriptions\Subscriptions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A business's AI team: which agents it has, the brand profile they share
 * and what each one was told to remember. Rows are always looked up by
 * tenant_id, so the platform level can manage any business; nothing of one
 * business ever reaches another's agents.
 */
class Agents
{
    /** Characters of notes an agent keeps per business (the oldest go first). */
    public const MAX_NOTES = 3000;

    public function __construct(
        private readonly SupportChat $chat,
        private readonly Subscriptions $subscriptions,
    ) {}

    /** @return Collection<string, TenantAgent> the business's agent rows by key */
    public function rows(Tenant $tenant): Collection
    {
        return TenantAgent::withoutTenancy()->where('tenant_id', $tenant->id)->get()->keyBy('agent_key');
    }

    public function isActive(Tenant $tenant, Agent $agent): bool
    {
        return TenantAgent::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->where('agent_key', $agent->key())
            ->where('active', true)
            ->exists();
    }

    /** @return list<string> keys of the agents the business has */
    public function activeKeys(Tenant $tenant): array
    {
        return $this->rows($tenant)->filter(fn (TenantAgent $row) => $row->active && $row->agent())->keys()->values()->all();
    }

    /**
     * Turns an agent on or off for a business (super admin). On: the
     * business is told in its chat and the bell, with a link to the agent.
     */
    public function setActive(Tenant $tenant, Agent $agent, bool $active, ?User $by = null): TenantAgent
    {
        $row = $this->row($tenant, $agent);
        $was = $row->active;

        $row->forceFill([
            'active' => $active,
            'activated_at' => $active ? ($was ? $row->activated_at : now()) : $row->activated_at,
            'activated_by' => $active && ! $was ? $by?->id : $row->activated_by,
        ])->save();

        if ($active && ! $was) {
            $this->chat->notify($tenant, sprintf(
                'Ձեր թիմին միացավ նոր AI աշխատակից՝ %s։ Բացեք «AI օգնական» բաժինը և սկսեք զրույցը. նախ պատմեք ձեր բիզնեսի մասին, որպեսզի նա աշխատի հենց ձեր բրենդի համար։',
                $agent->label(),
            ), [
                'type' => NotificationType::Agent,
                'title' => sprintf('Նոր AI աշխատակից՝ %s', $agent->label()),
                'url' => '/admin/assistant?agent='.$agent->key(),
            ]);
        }

        return $row;
    }

    public function profile(Tenant $tenant): BrandProfile
    {
        return BrandProfile::withoutTenancy()->where('tenant_id', $tenant->id)->first()
            ?? new BrandProfile(['colors' => []]);
    }

    /**
     * Saves the fields given (others stay): from the form or from an agent
     * in the chat. Texts are trimmed and cut; colours are kept as #RRGGBB.
     *
     * @param  array<string, mixed>  $data  snake_case fields of BrandProfile
     */
    public function saveProfile(Tenant $tenant, array $data, ?User $by = null): BrandProfile
    {
        return DB::transaction(function () use ($tenant, $data, $by) {
            $profile = BrandProfile::withoutTenancy()->where('tenant_id', $tenant->id)->lockForUpdate()->first()
                ?? new BrandProfile;
            $profile->tenant_id = $tenant->id;

            foreach (BrandProfile::FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $profile->{$field} = self::text($data[$field], BrandProfile::MAX_TEXT);
                }
            }
            if (array_key_exists('colors', $data)) {
                $profile->colors = self::colors($data['colors']);
            }
            $profile->updated_by = $by?->id ?? $profile->updated_by;
            $profile->save();

            return $profile;
        });
    }

    /** Adds a line to what the agent remembers about the business. */
    public function remember(Tenant $tenant, Agent $agent, string $note): TenantAgent
    {
        $note = self::text($note, 500);
        $row = $this->row($tenant, $agent);

        if ($note === null) {
            return $row;
        }

        $notes = trim(($row->notes ? $row->notes."\n" : '').'- '.$note);
        // The newest lines stay when it grows too long.
        while (mb_strlen($notes) > self::MAX_NOTES && str_contains($notes, "\n")) {
            $notes = substr($notes, strpos($notes, "\n") + 1);
        }
        $row->forceFill(['notes' => mb_substr($notes, -self::MAX_NOTES)])->save();

        return $row;
    }

    /** The owner edits (or clears) what the agent remembers. */
    public function setNotes(Tenant $tenant, Agent $agent, ?string $notes): TenantAgent
    {
        $row = $this->row($tenant, $agent);
        $row->forceFill(['notes' => self::text($notes, self::MAX_NOTES)])->save();

        return $row;
    }

    /**
     * Every agent of the catalog with what this business has of it: on or
     * off, its price (included in the plan or not) and its notes.
     *
     * @return list<array<string, mixed>>
     */
    public function overview(Tenant $tenant): array
    {
        $rows = $this->rows($tenant);
        $prices = ModulePrice::map();
        $plan = $this->subscriptions->for($tenant)->plan;

        return array_values(array_map(function (Agent $agent) use ($rows, $prices, $plan) {
            $row = $rows->get($agent->key());

            return [
                ...$agent->present(),
                'active' => (bool) $row?->active,
                'activatedAt' => $row?->active ? $row->activated_at?->toIso8601String() : null,
                'price' => $prices[$agent->priceKey()] ?? $agent->defaultPrice(),
                'included' => (bool) $plan?->includes($agent->priceKey()),
                'notes' => $row?->notes,
            ];
        }, AgentCatalog::all()));
    }

    private function row(Tenant $tenant, Agent $agent): TenantAgent
    {
        $row = TenantAgent::withoutTenancy()->where('tenant_id', $tenant->id)->where('agent_key', $agent->key())->first();

        if ($row) {
            return $row;
        }

        // tenant_id isn't fillable: set it, not the current business's.
        $row = new TenantAgent(['agent_key' => $agent->key(), 'active' => false]);
        $row->tenant_id = $tenant->id;

        try {
            // A savepoint inside a caller's transaction, so Postgres doesn't abort it.
            DB::transaction(fn () => $row->save());
        } catch (UniqueConstraintViolationException) {
            // Another request made it first: use that one.
            return TenantAgent::withoutTenancy()->where('tenant_id', $tenant->id)->where('agent_key', $agent->key())->firstOrFail();
        }

        return $row;
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $text = trim(preg_replace("/[ \t]+/u", ' ', str_replace("\r", '', (string) $value)) ?? '');

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    /**
     * ["#3F6F62", {hex: "b47a2b", name: "Ոսկեգույն"}] → [{hex: "#3F6F62", name: null}, ...]
     *
     * @return list<array{hex: string, name: string|null}>
     */
    private static function colors(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $colors = [];
        foreach ($value as $item) {
            $hex = is_array($item) ? ($item['hex'] ?? null) : $item;
            $name = is_array($item) ? self::text($item['name'] ?? null, 40) : null;
            if (! is_string($hex) || ! preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($hex), $m)) {
                continue;
            }
            $digits = strlen($m[1]) === 3 ? preg_replace('/(.)/', '$1$1', $m[1]) : $m[1];
            $colors['#'.strtoupper($digits)] ??= ['hex' => '#'.strtoupper($digits), 'name' => $name];
        }

        return array_slice(array_values($colors), 0, BrandProfile::MAX_COLORS);
    }
}
