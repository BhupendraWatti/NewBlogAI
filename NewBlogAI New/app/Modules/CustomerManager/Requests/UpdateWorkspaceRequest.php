<?php

namespace App\Modules\CustomerManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy-level authorization is handled in the controller.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
