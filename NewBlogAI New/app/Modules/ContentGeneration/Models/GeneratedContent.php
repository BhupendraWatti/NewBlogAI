<?php

namespace App\Modules\ContentGeneration\Models;

use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class GeneratedContent extends Model
{
    protected $table = 'generated_contents';

    protected $fillable = [
        'pipeline_id',
        'site_id',
        'topic_id',
        'title',
        'content',
        'status', // draft, pending_review, approved, rejected, published
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(ContentPipeline::class, 'pipeline_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class, 'generated_content_id');
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
