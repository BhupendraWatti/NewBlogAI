<?php

namespace App\Modules\SiteManager\Models;

use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\ScheduleManager\Models\PublishingSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Site extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'domain_url',
        'api_key',
        'key_id',
        'is_active',
        'status',
        'plugin_version',
        'is_default',
        'last_synced_at',
        'last_sync_status',
        'error_log', 'publishing_mode',
        'category_mapping',
        'sync_settings',
        'timezone',
        'configuration_version',
        'workspace_id',
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
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'category_mapping' => 'array',
            'sync_settings' => 'array',
            'configuration_version' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(ContentPipeline::class, 'site_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PublishingSchedule::class, 'site_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CustomerManager\Models\Workspace::class, 'workspace_id');
    }


    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('analytics_content_stats');
            Cache::forget('analytics_ai_stats');
        });

        static::deleted(function () {
            Cache::forget('analytics_content_stats');
            Cache::forget('analytics_ai_stats');
        });
    }
}
