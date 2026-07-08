<?php

namespace App\Modules\ContentPipeline\Models;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPipeline extends Model
{
    protected $table = 'content_pipelines';

    protected $fillable = [
        'site_id',
        'news_category',
        'prompt_id',
        'ai_provider_id',
        'language',
        'generation_type',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'prompt_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PipelineRun::class, 'pipeline_id');
    }
}
