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
            'site_id'         => ['required', 'integer', 'exists:sites,id'],
            'news_category'   => ['required', 'string', 'in:global,trending,local,technology,business,politics,sports,health,science,entertainment'],
            'prompt_id'       => ['required', 'integer', 'exists:prompts,id'],
            'ai_provider_id'  => ['required', 'integer', 'exists:ai_providers,id'],
            'language'        => ['sometimes', 'string', 'max:10'],
            'generation_type' => ['sometimes', 'string', 'in:news,newsletter,summary'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }
}
