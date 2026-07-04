<?php

namespace App\Modules\SubscriptionManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'billing_period' => $this->billing_period,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'limits' => $this->limits ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'customer' => $this->relationLoaded('customer') ? [
                'id' => $this->customer?->id,
                'company_name' => $this->customer?->company_name,
                'email' => $this->customer?->email,
            ] : null,
        ];
    }
}
