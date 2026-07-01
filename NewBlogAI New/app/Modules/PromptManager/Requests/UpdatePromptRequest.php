<?php

namespace App\Modules\PromptManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'promt' => ['sometimes', 'required', 'string'],
        ];
    }
}
