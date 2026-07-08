<?php

namespace App\Modules\CustomerManager\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public const ROLES = ['Owner', 'Admin', 'Editor', 'Writer', 'Reviewer', 'Publisher'];

    public function authorize(): bool
    {
        return true; // Policy-level authorization is handled in the controller.
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', Rule::in(self::ROLES)],
        ];
    }
}
