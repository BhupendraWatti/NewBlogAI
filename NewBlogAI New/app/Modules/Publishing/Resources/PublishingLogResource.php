<?php

namespace App\Modules\Publishing\Resources;

use App\Modules\ContentGeneration\Resources\GeneratedContentResource;
use App\Modules\SiteManager\Resources\SiteResource;
use App\Modules\AuthManager\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublishingLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'generated_content_id' => $this->generated_content_id,
            'site_id'              => $this->site_id,
            'user_id'              => $this->user_id,
            'status'               => $this->status,
            'wp_post_id'           => $this->wp_post_id,
            'published_url'        => $this->published_url,
            'wp_status'            => $this->wp_status,
            'scheduled_at'         => $this->scheduled_at,
            'started_at'           => $this->started_at,
            'completed_at'         => $this->completed_at,
            'error_message'        => $this->error_message,
            'retry_count'          => $this->retry_count,
            'content'              => new GeneratedContentResource($this->whenLoaded('content')),
            'site'                 => new SiteResource($this->whenLoaded('site')),
            'author'               => new UserResource($this->whenLoaded('author')),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
