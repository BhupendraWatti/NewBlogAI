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
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('domain_url')) {
            $this->merge([
                'domain_url' => rtrim((string) $this->input('domain_url'), '/'),
            ]);
        }
    }

    public function rules(): array
    {
        $siteId = $this->route('site') ?? $this->route('id');

        return [
            'customer_id'                => ['sometimes', 'nullable', 'uuid', 'exists:customers,id'],
            'name'                       => ['sometimes', 'nullable', 'string', 'max:255'],
            'domain_url'                 => ['sometimes', 'required', 'url', 'unique:sites,domain_url,' . $siteId],
            'api_key'                    => ['nullable', 'string'],
            'key_id'                     => ['nullable', 'exists:keys,id'],
            'selected_topics'            => ['nullable', 'array'],
            'selected_topics.*.topic'    => ['required', 'string'],
            'selected_topics.*.promt_id' => ['nullable', 'integer', 'exists:promts,id'],
            'promt_id'                   => ['nullable', 'exists:promts,id'],
            'slot'                       => ['nullable', 'string'],
            'is_active'                  => ['sometimes', 'boolean'],
            'is_default'                 => ['sometimes', 'boolean'],
            'publishing_mode'            => ['sometimes', 'string', 'in:draft,review,publish'],
            'category_mapping'           => ['nullable', 'array'],
            'sync_settings'              => ['nullable', 'array'],
            'timezone'                   => ['sometimes', 'timezone'],
        ];
    }
}
