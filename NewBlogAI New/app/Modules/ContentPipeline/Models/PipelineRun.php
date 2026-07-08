<?php

namespace App\Modules\ContentPipeline\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineRun extends Model
{
    /** Standard full content generation run (legacy default). */
    public const TYPE_FULL = 'full';

    /** Coverage discovery run: produces 9 news candidates, stops at 'ready'. */
    public const TYPE_DISCOVERY = 'discovery';

    /** Discovery-only status: candidates generated, awaiting employee selection. */
    public const STATUS_READY = 'ready';

    protected $table = 'pipeline_runs';

    protected $fillable = [
        'pipeline_id',
        'status',
        'run_type',
        'retry_count',
        'error_message',
        'properties',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'run_type' => self::TYPE_FULL,
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(ContentPipeline::class, 'pipeline_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(NewsCandidate::class, 'pipeline_run_id')->orderBy('position');
    }

    public function isDiscovery(): bool
    {
        return $this->run_type === self::TYPE_DISCOVERY;
    }
}
