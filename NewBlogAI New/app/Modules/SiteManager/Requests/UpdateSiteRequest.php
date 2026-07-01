<?php

namespace App\Modules\SiteManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $siteId = $this->route('site') ?? $this->route('id');

        return [
            'domain_url'      => ['sometimes', 'required', 'url', 'unique:sites,domain_url,' . $siteId],
            'api_key'         => ['nullable', 'string'],
            'key_id'          => ['nullable', 'exists:keys,id'],
            'selected_topics' => ['nullable', 'array'],
            'selected_topics.*.topic'    => ['required', 'string'],
            'selected_topics.*.promt_id' => ['nullable', 'integer', 'exists:promts,id'],
            'promt_id'        => ['nullable', 'exists:promts,id'],
            'slot'            => ['nullable', 'string'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }
}
