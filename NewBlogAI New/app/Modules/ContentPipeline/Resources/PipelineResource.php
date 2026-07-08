<?php

namespace App\Modules\ContentPipeline\Resources;

use App\Modules\AIProviderManager\Resources\AIProviderResource;
use App\Modules\PromptManager\Resources\PromptResource;
use App\Modules\SiteManager\Resources\SiteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'site_id'         => $this->site_id,
            'news_category'   => $this->news_category,
            'prompt_id'       => $this->prompt_id,
            'ai_provider_id'  => $this->ai_provider_id,
            'language'        => $this->language,
            'generation_type' => $this->generation_type,
            'status'          => $this->status,
            'is_active'       => (bool) $this->is_active,
            'site'            => new SiteResource($this->whenLoaded('site')),
            'prompt'          => new PromptResource($this->whenLoaded('prompt')),
            'provider'        => new AIProviderResource($this->whenLoaded('provider')),
            'runs'            => PipelineRunResource::collection($this->whenLoaded('runs')),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
