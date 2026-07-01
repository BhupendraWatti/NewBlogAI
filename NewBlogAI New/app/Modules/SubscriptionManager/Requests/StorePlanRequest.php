<?php

namespace App\Modules\SubscriptionManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                      => ['required', 'string', 'max:255'],
            'monthly_price'             => ['required', 'numeric', 'min:0'],
            'yearly_price'              => ['required', 'numeric', 'min:0'],
            'max_wordpress_sites'       => ['required', 'integer', 'min:1'],
            'max_topics'                => ['required', 'integer', 'min:1'],
            'publishing_schedule_limit' => ['required', 'integer', 'min:1'],
            'max_articles_per_day'      => ['required', 'integer', 'min:1'],
            'prompt_templates_allowed'  => ['required', 'integer', 'min:1'],
            'ai_providers_available'    => ['nullable', 'array'],
            'ai_providers_available.*'  => ['required', 'string'],
            'api_keys_allowed'          => ['required', 'integer', 'min:1'],
            'storage_limit'             => ['required', 'integer', 'min:1'],
            'analytics_access'          => ['nullable', 'boolean'],
            'priority_support'          => ['nullable', 'boolean'],
            'status'                    => ['nullable', 'string', 'in:active,inactive']
        ];
    }
}
