<?php

namespace App\Modules\CustomerManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'owner_name' => $this->owner_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'company_logo' => $this->company_logo,
            'website' => $this->website,
            'industry' => $this->industry,
            'status' => $this->status,
            'tags' => $this->tags ?? [],
            'health_score' => $this->health_score,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'notes' => CustomerNoteResource::collection($this->whenLoaded('notes')),
            'activities' => CustomerActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
