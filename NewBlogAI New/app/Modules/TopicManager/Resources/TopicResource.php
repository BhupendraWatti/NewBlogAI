<?php

namespace App\Modules\TopicManager\Resources;

use App\Modules\PromptManager\Resources\PromptResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'parent_id'            => $this->parent_id,
            'name'                 => $this->name,
            'category'             => $this->category,
            'priority'             => $this->priority,
            'language'             => $this->language,
            'status'               => $this->status,
            'generation_frequency' => $this->generation_frequency,
            'tags'                 => $this->tags ?? [],
            'prompt_id'            => $this->prompt_id,
            'parent'               => new self($this->whenLoaded('parent')),
            'prompt'               => new PromptResource($this->whenLoaded('prompt')),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
