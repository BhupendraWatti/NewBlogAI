<?php

namespace App\Modules\Publishing\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BulkPublishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->role, [1, 2], true);
    }

    public function rules(): array
    {
        return [
            'article_ids'   => ['required', 'array'],
            'article_ids.*' => ['integer', 'exists:generated_contents,id'],
            'site_id'       => ['nullable', 'integer', 'exists:sites,id'],
            'wp_status'     => ['sometimes', 'required', 'string', 'in:draft,publish,pending,future'],
        ];
    }
}
