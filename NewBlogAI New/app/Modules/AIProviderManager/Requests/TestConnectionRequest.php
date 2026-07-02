<?php

namespace App\Modules\AIProviderManager\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TestConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->role, [1, 2], true);
    }

    public function rules(): array
    {
        return [
            'api_key' => ['sometimes', 'required', 'string'],
            'model'   => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
