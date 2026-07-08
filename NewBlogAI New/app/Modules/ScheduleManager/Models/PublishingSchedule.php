<?php

namespace App\Modules\ScheduleManager\Models;

use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishingSchedule extends Model
{
    protected $fillable = [
        'site_id',
        'pipeline_id',
        'name',
        'frequency',
        'timezone',
        'time_of_day',
        'days_of_week',
        'is_active',
        'schedule_mode',
        'metadata',
        'next_run_at',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(ContentPipeline::class);
    }
}
