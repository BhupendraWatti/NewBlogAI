<?php

namespace App\Modules\MediaManager\Models;

use App\Modules\ContentGeneration\Models\GeneratedContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaItem extends Model
{
    protected $table = 'media_items';

    protected $fillable = [
        'generated_content_id',
        'filename',
        'filepath',
        'url',
        'driver',
        'prompt',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the generated content associated with this media item.
     */
    public function generatedContent(): BelongsTo
    {
        return $this->belongsTo(GeneratedContent::class, 'generated_content_id');
    }
}
