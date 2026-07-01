<?php

namespace App\Modules\PromptManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'content'    => $this->promt,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
