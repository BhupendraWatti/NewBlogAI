<?php

namespace App\Modules\ContentPipeline\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['sometimes', 'required', 'integer', 'exists:sites,id'],
            'topic_id' => ['sometimes', 'required', 'integer', 'exists:topics,id'],
            'prompt_id' => ['sometimes', 'required', 'integer', 'exists:prompts,id'],
            'ai_provider_id' => ['sometimes', 'required', 'integer', 'exists:ai_providers,id'],
            'language' => ['sometimes', 'required', 'string', 'max:10'],
            'generation_type' => ['sometimes', 'required', 'string', 'in:article,newsletter,blog,summary'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
