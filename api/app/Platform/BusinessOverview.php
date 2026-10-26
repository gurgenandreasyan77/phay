<?php

namespace App\Platform;

use App\Agents\Agent;
use App\Agents\AgentCatalog;
use App\Agents\Agents;
use App\Assistant\AssistantQuota;
use App\Enums\BusinessRole;
use App\Features\FeatureFlag;
use App\Http\Resources\Admin\SubscriptionPaymentResource;
use App\Http\Resources\Admin\SubscriptionResource;
use App\Models\AssistantBonus;
use App\Models\Employee;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Subscriptions\Subscriptions;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Laravel\Pennant\Feature;

/**
 * One business as the platform team sees it: how long and how actively it
 * uses Phay, its subscription, modules, AI agents and AI usage, how many
 * people it has, and the chat with it. Never its content: no orders, sales,
 * clients, tables, money figures, AI conversations, agents' notes or brand
 * profile. Payments and bonuses (the platform's money) only for the super
 * admin, as on the subscriptions screen.
 */
class BusinessOverview
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly AssistantQuota $quota,
        private readonly Agents $agents,
        private readonly BusinessActivity $activity,
        private readonly TenantContext $context,
    ) {}

    /** @return array<string, mixed> */
    public function for(Tenant $tenant, User $viewer): array
    {
        $superAdmin = (bool) $viewer->is_super_admin;
        $subscription = $this->subscriptions->sync($tenant, $this->subscriptions->for($tenant)->refresh());
        $subscription->setRelation('tenant', $tenant);
        $assistant = $this->quota->status($tenant);
        $activeAgents = $this->agents->activeKeys($tenant);
        $payments = SubscriptionPayment::query()->with('user:id,name')->where('tenant_id', $tenant->id);

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'logo' => $tenant->logo,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'defaultLocale' => $tenant->default_locale,
                'timezone' => $tenant->timezone,
                'createdAt' => $tenant->created_at?->toIso8601String(),
            ],
            'activity' => $this->activity->summary($tenant),
            'subscription' => (new SubscriptionResource($subscription))->resolve(),
            'payments' => $superAdmin ? [
                'count' => (clone $payments)->count(),
                'total' => (int) (clone $payments)->sum('amount'),
                'latest' => SubscriptionPaymentResource::collection((clone $payments)->latest('id')->limit(10)->get())->resolve(),
            ] : null,
            'modules' => $this->context->run($tenant, fn () => array_map(fn (FeatureFlag $flag) => [
                'key' => $flag->value,
                'label' => $flag->label(),
                'active' => Feature::active($flag->value),
            ], FeatureFlag::cases())),
            'agents' => array_values(array_map(fn (Agent $agent) => [
                'key' => $agent->key(),
                'persona' => $agent->persona(),
                'label' => $agent->label(),
                'active' => in_array($agent->key(), $activeAgents, true),
            ], AgentCatalog::all())),
            'assistant' => [
                ...$assistant,
                'bonuses' => $superAdmin
                    ? AssistantBonus::query()->with('user:id,name')->where('tenant_id', $tenant->id)->latest('id')->limit(10)->get()
                        ->map(fn (AssistantBonus $b) => [
                            'id' => $b->id,
                            'amount' => $b->amount,
                            'note' => $b->note,
                            'givenBy' => $b->user?->name,
                            'createdAt' => $b->created_at?->toIso8601String(),
                        ])->all()
                    : null,
            ],
            'people' => $this->people($tenant),
            'support' => [
                'unread' => $tenant->supportMessages()->unreadFrom(false)->count(),
                'lastMessageAt' => ($last = $tenant->supportMessages()->max('created_at'))
                    ? Carbon::parse($last)->toIso8601String()
                    : null,
            ],
            'sites' => $tenant->sites()->count(),
        ];
    }

    /** Counts only: logins by role and employees, no names. */
    private function people(Tenant $tenant): array
    {
        $logins = $tenant->users()
            ->where('users.is_super_admin', false)
            ->where('users.is_platform_moderator', false)
            ->get(['users.id', 'users.password']);

        $byRole = array_fill_keys(BusinessRole::values(), 0);
        foreach ($logins as $login) {
            $role = BusinessRole::tryFrom((string) $login->pivot->role) ?? BusinessRole::Moderator;
            $byRole[$role->value]++;
        }

        return [
            'logins' => $logins->count(),
            'activated' => $logins->filter(fn (User $user) => $user->isActivated())->count(),
            'byRole' => $byRole,
            'employees' => Employee::withoutTenancy()->where('tenant_id', $tenant->id)->count(),
        ];
    }
}
