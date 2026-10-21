<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            // AMD per month.
            'price' => $this->price,
            'trialDays' => $this->trial_days,
            // AI assistant requests a subscription month (null: no limit).
            'assistantRequests' => $this->assistant_requests,
            // Modules whose price the plan already includes.
            'includedModules' => $this->included_modules ?? [],
            'isDefault' => $this->is_default,
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
            // Businesses on it (platform list only).
            'subscriptionsCount' => $this->whenCounted('subscriptions'),
        ];
    }
}
