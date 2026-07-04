<?php

namespace App\Modules\SiteManager\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Promt;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'domain_url',
        'api_key',
        'key_id',
        'selected_topics',
        'promt_id',
        'slot',
        'is_active',
        'status',
        'plugin_version',
        'is_default',
        'last_synced_at',
        'last_sync_status',
        'error_log'
        ,'publishing_mode',
        'category_mapping',
        'sync_settings',
        'timezone',
        'configuration_version',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'selected_topics' => 'array',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'category_mapping' => 'array',
            'sync_settings' => 'array',
            'configuration_version' => 'integer',
        ];
    }

    public function promt()
    {
        return $this->belongsTo(Promt::class, 'promt_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CustomerManager\Models\Customer::class, 'customer_id');
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(\App\Modules\ContentPipeline\Models\ContentPipeline::class, 'site_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(\App\Modules\ScheduleManager\Models\PublishingSchedule::class, 'site_id');
    }

    protected static function booted()
    {
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('analytics_content_stats');
            \Illuminate\Support\Facades\Cache::forget('analytics_ai_stats');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('analytics_content_stats');
            \Illuminate\Support\Facades\Cache::forget('analytics_ai_stats');
        });
    }
}
