<?php

namespace App\Modules\CustomerManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'customer_id' => $this->customer_id,
            'user_id'     => $this->user_id,
            'event_type'  => $this->event_type,
            'description' => $this->description,
            'properties'  => $this->properties ?? [],
            'created_at'  => $this->created_at?->toIso8601String()
        ];
    }
}
