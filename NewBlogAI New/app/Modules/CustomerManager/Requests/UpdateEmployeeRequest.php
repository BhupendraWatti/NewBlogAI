<?php

namespace App\Modules\CustomerManager\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy-level authorization is handled in the controller.
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(StoreEmployeeRequest::ROLES)],
        ];
    }
}
