<?php

namespace App\Modules\Licensing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string'],
            'domain'      => ['required', 'string', 'url'],
        ];
    }
}
