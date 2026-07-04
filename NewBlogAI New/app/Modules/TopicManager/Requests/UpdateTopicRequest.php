<?php

namespace App\Modules\TopicManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subscription_id'      => ['sometimes', 'nullable', 'integer', 'exists:subscriptions,id'],
            'name'                 => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id'            => ['sometimes', 'nullable', 'integer', 'exists:topics,id'],
            'category'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'priority'             => ['sometimes', 'required', 'string', 'in:low,medium,high'],
            'language'             => ['sometimes', 'required', 'string', 'max:10'],
            'status'               => ['sometimes', 'required', 'string', 'in:active,inactive,draft'],
            'generation_frequency' => ['sometimes', 'required', 'string', 'max:255'],
            'tags'                 => ['sometimes', 'nullable', 'array'],
            'tags.*'               => ['string'],
            'prompt_id'            => ['sometimes', 'nullable', 'integer', 'exists:promts,id'],
        ];
    }
}
