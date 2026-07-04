<?php

namespace App\Modules\Publishing\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PublishArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'wp_status' => ['sometimes', 'required', 'string', 'in:draft,publish,pending,future'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
