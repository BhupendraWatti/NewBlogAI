<?php

namespace App\Modules\ContentGeneration\Resources;

use App\Modules\ContentPipeline\Resources\PipelineResource;
use App\Modules\SiteManager\Resources\SiteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneratedContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipeline_id,
            'site_id' => $this->site_id,
            'topic_id' => $this->topic_id,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status,
            'metadata' => $this->metadata ?? [],
            'news_category' => $this->pipeline?->news_category ?? 'global',
            'site' => new SiteResource($this->whenLoaded('site')),
            'pipeline' => new PipelineResource($this->whenLoaded('pipeline')),
            'revisions' => ContentRevisionResource::collection($this->whenLoaded('revisions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
