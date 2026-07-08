<?php

namespace App\Modules\ContentGeneration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->role, [1, 2], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:generated,draft,pending_review,approved,scheduled,published,rejected,failed'],
        ];
    }
}
