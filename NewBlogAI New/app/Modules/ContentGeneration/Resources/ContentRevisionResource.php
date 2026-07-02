<?php

namespace App\Modules\ContentGeneration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'generated_content_id' => $this->generated_content_id,
            'title'                => $this->title,
            'content'              => $this->content,
            'user_id'              => $this->user_id,
            'created_at'           => $this->created_at,
        ];
    }
}
