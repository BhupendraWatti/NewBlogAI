<?php

namespace App\Modules\AIProviderManager\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->role, [1, 2], true);
    }

    public function rules(): array
    {
        return [
            'provider_key' => ['required', 'string', 'unique:ai_providers,provider_key', 'in:gemini,openai,claude,groq,openrouter,ollama'],
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string'],
            'default_model' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
