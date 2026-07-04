<?php

namespace App\Modules\ContentGeneration\Models;

use App\Modules\PromptManager\Models\Prompt;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIRequestLog extends Model
{
    protected $table = 'ai_request_logs';

    protected $fillable = [
        'provider',
        'customer_id',
        'subscription_id',
        'site_id',
        'model',
        'prompt_id',
        'topic_id',
        'execution_time_ms',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
        'status',
        'response_metadata',
        'error_log',
    ];

    protected $casts = [
        'response_metadata' => 'array',
        'estimated_cost' => 'float',
    ];

    public $timestamps = false;

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'prompt_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }
}
