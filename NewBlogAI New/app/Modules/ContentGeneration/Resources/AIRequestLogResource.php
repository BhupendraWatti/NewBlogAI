<?php

namespace App\Modules\ContentGeneration\Resources;

use App\Modules\PromptManager\Resources\PromptResource;
use App\Modules\TopicManager\Resources\TopicResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIRequestLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_id' => $this->prompt_id,
            'topic_id' => $this->topic_id,
            'execution_time_ms' => $this->execution_time_ms,
            'prompt_tokens' => $this->prompt_tokens,
            'completion_tokens' => $this->completion_tokens,
            'total_tokens' => $this->total_tokens,
            'estimated_cost' => (float) $this->estimated_cost,
            'status' => $this->status,
            'response_metadata' => $this->response_metadata ?? [],
            'error_log' => $this->error_log,
            'created_at' => $this->created_at,
            'prompt' => new PromptResource($this->whenLoaded('prompt')),
            'topic' => new TopicResource($this->whenLoaded('topic')),
        ];
    }
}
