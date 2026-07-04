<?php

namespace App\Modules\ContentPipeline\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'prompt_id' => ['required', 'integer', 'exists:promts,id'],
            'ai_provider_id' => ['required', 'integer', 'exists:ai_providers,id'],
            'language' => ['sometimes', 'string', 'max:10'],
            'generation_type' => ['sometimes', 'string', 'in:article,newsletter,blog,summary'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
