<?php

namespace App\Modules\SubscriptionManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'name'                      => $this->name,
            'monthly_price'             => (float)$this->monthly_price,
            'yearly_price'              => (float)$this->yearly_price,
            'max_wordpress_sites'       => $this->max_wordpress_sites,
            'max_topics'                => $this->max_topics,
            'publishing_schedule_limit' => $this->publishing_schedule_limit,
            'max_articles_per_day'      => $this->max_articles_per_day,
            'prompt_templates_allowed'  => $this->prompt_templates_allowed,
            'ai_providers_available'    => $this->ai_providers_available ?? [],
            'api_keys_allowed'          => $this->api_keys_allowed,
            'storage_limit'             => $this->storage_limit,
            'analytics_access'          => (bool)$this->analytics_access,
            'priority_support'          => (bool)$this->priority_support,
            'status'                    => $this->status,
            'created_at'                => $this->created_at?->toIso8601String(),
            'updated_at'                => $this->updated_at?->toIso8601String()
        ];
    }
}
