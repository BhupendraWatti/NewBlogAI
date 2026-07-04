<?php

namespace App\Modules\SiteManager\Resources;

use App\Modules\PromptManager\Resources\PromptResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'customer_id'      => $this->customer_id,
            'name'             => $this->name,
            'domain_url'       => $this->domain_url,
            'has_api_key'      => !empty($this->api_key),
            'key_id'           => $this->key_id,
            'selected_topics'  => $this->selected_topics ?? [],
            'promt_id'         => $this->promt_id,
            'slot'             => $this->slot,
            'is_active'        => (bool) $this->is_active,
            'is_default'       => (bool) $this->is_default,
            'status'           => $this->status,
            'plugin_version'   => $this->plugin_version,
            'last_synced_at'   => $this->last_synced_at,
            'last_sync_status' => $this->last_sync_status,
            'error_log'        => $this->error_log,
            'publishing_mode'  => $this->publishing_mode,
            'category_mapping' => $this->category_mapping ?? [],
            'sync_settings'    => $this->sync_settings ?? [],
            'timezone'         => $this->timezone,
            'configuration_version' => $this->configuration_version,
            'promt'            => new PromptResource($this->whenLoaded('promt')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
