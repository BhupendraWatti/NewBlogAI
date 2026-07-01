<?php

namespace App\Modules\SubscriptionManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'customer_id'    => $this->customer_id,
            'plan_id'        => $this->plan_id,
            'event_type'     => $this->event_type,
            'billing_period' => $this->billing_period,
            'amount_paid'    => (float)$this->amount_paid,
            'created_at'     => $this->created_at?->toIso8601String()
        ];
    }
}
