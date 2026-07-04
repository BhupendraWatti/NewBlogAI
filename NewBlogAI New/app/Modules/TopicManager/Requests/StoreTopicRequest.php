<?php

namespace App\Modules\TopicManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:topics,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high'],
            'language' => ['sometimes', 'string', 'max:10'],
            'status' => ['sometimes', 'string', 'in:active,inactive,draft'],
            'generation_frequency' => ['sometimes', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'prompt_id' => ['nullable', 'integer', 'exists:promts,id'],
        ];
    }
}
