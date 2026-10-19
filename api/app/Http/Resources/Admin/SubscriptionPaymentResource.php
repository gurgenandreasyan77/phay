<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'months' => $this->months,
            'periodFrom' => $this->period_from?->toIso8601String(),
            'periodTo' => $this->period_to?->toIso8601String(),
            'note' => $this->note,
            'recordedBy' => $this->whenLoaded('user', fn () => $this->user?->name),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
