<?php

namespace App\Modules\PromptManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'name' => ['required', 'string', 'max:255'],
            'promt' => ['required', 'string'],
            'category' => ['required', 'string', 'max:255'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string'],
            'version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
