<?php

namespace App\Modules\SystemSettings\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Settings can only be managed by Super Admin (role 1) or Admin (role 2)
        return Auth::check() && in_array((int) Auth::user()->role, [1, 2], true);
    }

    public function rules(): array
    {
        return [
            'currency' => ['sometimes', 'required', 'string', 'in:USD,INR'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
            'language' => ['sometimes', 'required', 'string', 'in:en,es,fr,de,hi'],
            'ai_default_provider' => ['sometimes', 'required', 'string', 'in:gemini,openai,claude,groq,openrouter,ollama'],
            'ai_default_model' => ['sometimes', 'required', 'string', 'max:255'],
            'image_generator_driver' => ['sometimes', 'required', 'string', 'in:pollinations,unsplash,dalle,dall-e'],
            'unsplash_access_key' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
