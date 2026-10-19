<?php

namespace App\Http\Resources\Admin;

use App\Models\Subscription;
use App\Subscriptions\Subscriptions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A business's subscription: where it stands, until when, and what the
 * business pays a month (its plan plus the modules it has on).
 *
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /** @param  array<string, int>|null  $prices  module prices, loaded once for a list */
    public function __construct(Subscription $resource, private readonly ?array $prices = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $subscriptions = app(Subscriptions::class);
        $status = $this->status();

        return [
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'plan' => $this->plan ? [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'price' => $this->plan->price,
            ] : null,
            'unlimited' => $this->unlimited,
            'trialEndsAt' => $this->trial_ends_at?->toIso8601String(),
            'paidUntil' => $this->paid_until?->toIso8601String(),
            'freeUntil' => $this->free_until?->toIso8601String(),
            'cancelAt' => $this->cancel_at?->toIso8601String(),
            // When access ends (null: never) and the days left until then.
            'endsAt' => $this->endsAt()?->toIso8601String(),
            'daysLeft' => $this->daysLeft(),
            // Within the warning window: the business sees a banner.
            'ending' => $this->isEnding($subscriptions->warnDays()),
            'warnDays' => $subscriptions->warnDays(),
            'pricing' => $subscriptions->pricing($this->tenant, $this->resource, $this->prices),
        ];
    }
}
