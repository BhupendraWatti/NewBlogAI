<?php

namespace App\Modules\ScheduleManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'pipeline_id' => $this->pipeline_id,
            'name' => $this->name,
            'frequency' => $this->frequency,
            'timezone' => $this->timezone,
            'time_of_day' => $this->time_of_day,
            'days_of_week' => $this->days_of_week ?? [],
            'is_active' => (bool) $this->is_active,
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'last_run_at' => $this->last_run_at?->toIso8601String(),
        ];
    }
}
