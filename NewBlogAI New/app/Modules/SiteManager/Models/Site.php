<?php

namespace App\Modules\SiteManager\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Promt;

class Site extends Model
{
    protected $fillable = [
        'customer_id',
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
        ];
    }

    public function promt()
    {
        return $this->belongsTo(Promt::class, 'promt_id');
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
