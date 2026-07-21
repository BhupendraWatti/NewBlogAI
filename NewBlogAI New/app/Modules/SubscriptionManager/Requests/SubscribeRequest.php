<?php

namespace App\Modules\SubscriptionManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_period' => ['required', 'string', 'in:monthly,yearly'],
            'payment_token' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:trial,active'],
        ];
    }
}
