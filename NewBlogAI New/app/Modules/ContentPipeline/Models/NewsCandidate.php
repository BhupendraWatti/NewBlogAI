<?php

namespace App\Modules\ContentPipeline\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NewsCandidate extends Model
{
    /** Candidate awaiting employee selection. */
    public const STATUS_CANDIDATE = 'candidate';

    /** Employee selected this candidate; full generation triggered. */
    public const STATUS_SELECTED = 'selected';

    /** Explicitly rejected by an employee. */
    public const STATUS_REJECTED = 'rejected';

    /** Flagged as overlapping previously published or sibling news. */
    public const STATUS_DUPLICATE = 'duplicate';

    protected $table = 'news_candidates';

    protected $fillable = [
        'pipeline_run_id',
        'full_run_id',
        'position',
        'title',
        'summary',
        'source_references',
        'keywords',
        'trend_score',
        'freshness_score',
        'uniqueness_hash',
        'metadata',
        'status',
        'selected_by',
        'selected_at',
    ];

    protected $casts = [
        'source_references' => 'array',
        'keywords' => 'array',
        'metadata' => 'array',
        'trend_score' => 'integer',
        'freshness_score' => 'integer',
        'position' => 'integer',
        'selected_at' => 'datetime',
    ];

    public function discoveryRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class, 'pipeline_run_id');
    }

    public function fullRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class, 'full_run_id');
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }

    /**
     * Compute the uniqueness hash for a headline (normalized sha256).
     */
    public static function hashTitle(string $title): string
    {
        $normalized = Str::of($title)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        return hash('sha256', $normalized);
    }

    public function isSelectable(): bool
    {
        return $this->status === self::STATUS_CANDIDATE;
    }
}
