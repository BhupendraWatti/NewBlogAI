<?php

namespace App\Modules\PromptManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic_id' => ['sometimes', 'nullable', 'integer', 'exists:topics,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'prompt' => ['sometimes', 'required', 'string'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'variables' => ['sometimes', 'nullable', 'array'],
            'variables.*' => ['string'],
            'version' => ['sometimes', 'required', 'string', 'max:50'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive'],
        ];
    }
}
