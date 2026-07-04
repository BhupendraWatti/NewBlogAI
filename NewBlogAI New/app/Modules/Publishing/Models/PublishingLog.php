<?php

namespace App\Modules\Publishing\Models;

use App\Models\User;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class PublishingLog extends Model
{
    protected $table = 'publishing_logs';

    protected $fillable = [
        'generated_content_id',
        'site_id',
        'user_id',
        'status', // pending, processing, completed, failed, cancelled, retrying
        'wp_post_id',
        'published_url',
        'wp_status', // draft, future, publish, pending
        'scheduled_at',
        'started_at',
        'completed_at',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(GeneratedContent::class, 'generated_content_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
