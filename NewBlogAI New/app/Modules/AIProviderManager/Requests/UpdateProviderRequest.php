<?php

namespace App\Modules\AIProviderManager\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->role, [1, 2], true);
    }

    public function rules(): array
    {
        $providerId = $this->route('provider');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'api_key' => ['sometimes', 'nullable', 'string'],
            'default_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
