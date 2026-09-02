<?php

namespace App\Modules\SystemSettings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMasterOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:topic,country,state'],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'integer', 'exists:master_options,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
