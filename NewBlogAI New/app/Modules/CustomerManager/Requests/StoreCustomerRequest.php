<?php

namespace App\Modules\CustomerManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'owner_name'   => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'country'      => ['nullable', 'string', 'max:100'],
            'timezone'     => ['nullable', 'string', 'max:100'],
            'language'     => ['nullable', 'string', 'max:10'],
            'company_logo' => ['nullable', 'string', 'max:255'], // URL or path
            'website'      => ['nullable', 'url', 'max:255'],
            'industry'     => ['nullable', 'string', 'max:255'],
            'status'       => ['nullable', 'string', 'in:trial,active,suspended,expired,cancelled,archived'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['required', 'string'],
            'notes'        => ['nullable', 'string']
        ];
    }
}
