<?php

namespace App\Modules\ContentPipeline\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineRun extends Model
{
    protected $table = 'pipeline_runs';

    protected $fillable = [
        'pipeline_id',
        'status',
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

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(ContentPipeline::class, 'pipeline_id');
    }
}
