<?php

namespace App\Modules\SiteManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only allow authenticated users
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'domain_url'      => ['required', 'url', 'unique:sites,domain_url'],
            'api_key'         => ['nullable', 'string'],
            'key_id'          => ['nullable', 'exists:keys,id'],
            'selected_topics' => ['nullable', 'array'],
            'selected_topics.*.topic'    => ['required', 'string'],
            'selected_topics.*.promt_id' => ['nullable', 'integer', 'exists:promts,id'],
            'promt_id'        => ['nullable', 'exists:promts,id'],
            'slot'            => ['nullable', 'string'],
        ];
    }
}
