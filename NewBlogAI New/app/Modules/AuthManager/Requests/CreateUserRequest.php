<?php

namespace App\Modules\AuthManager\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->role, [1, 2], true);
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', 'integer', 'in:1,2,3,4'], // SuperAdmin=1, Admin=2, Support=3, User=4
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
        ];
    }
}
