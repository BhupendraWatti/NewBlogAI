<?php

namespace App\Modules\ContentPipeline\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'pipeline_run_id'   => $this->pipeline_run_id,
            'full_run_id'       => $this->full_run_id,
            'position'          => $this->position,
            'title'             => $this->title,
            'summary'           => $this->summary,
            'source_references' => $this->source_references ?? [],
            'keywords'          => $this->keywords ?? [],
            'trend_score'       => $this->trend_score,
            'freshness_score'   => $this->freshness_score,
            // NER Audit Panel fields — required by frontend renderCandidates
            'quality_score'         => $this->quality_score,
            'geo_city'              => $this->geo_city,
            'geo_state'             => $this->geo_state,
            'published_at_relative' => $this->metadata['published_at_relative'] ?? null,
            'event_date'            => $this->metadata['event_date'] ?? null,
            'status'                => $this->status,
            'selected_by'           => $this->selected_by,
            'selected_at'           => $this->selected_at?->toIso8601String(),
            'metadata'              => $this->metadata,
            'created_at'            => $this->created_at?->toIso8601String(),
        ];
    }
}
