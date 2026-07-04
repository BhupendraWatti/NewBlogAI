<?php

namespace App\Modules\ScheduleManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'pipeline_id' => ['nullable', 'integer', 'exists:content_pipelines,id'],
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', 'in:hourly,twice_daily,daily,weekly,monthly'],
            'timezone' => ['nullable', 'timezone'],
            'time_of_day' => ['nullable', 'date_format:H:i'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
