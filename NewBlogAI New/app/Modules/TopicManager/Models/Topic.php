<?php

namespace App\Modules\TopicManager\Models;

use App\Modules\PromptManager\Models\Prompt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use SoftDeletes;

    protected $table = 'topics';

    protected $fillable = [
        'parent_id',
        'subscription_id',
        'name',
        'category',
        'priority',
        'language',
        'status',
        'generation_frequency',
        'tags',
        'prompt_id',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    /**
     * Parent topic (for subcategories).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Subcategories of this topic.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Associated prompt template.
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'prompt_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\SubscriptionManager\Models\Subscription::class, 'subscription_id');
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class, 'topic_id');
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
