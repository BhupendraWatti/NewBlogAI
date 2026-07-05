<?php

namespace App\Modules\AIProviderManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_key' => $this->provider_key,
            'name' => $this->name,
            'has_api_key' => ! empty($this->api_key),
            'api_key' => $this->getMaskedApiKey(),
            'default_model' => $this->default_model,
            'is_default' => (bool) $this->is_default,
            'is_enabled' => (bool) $this->is_enabled,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
