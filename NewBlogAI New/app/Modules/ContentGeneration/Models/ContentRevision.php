<?php

namespace App\Modules\ContentGeneration\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRevision extends Model
{
    protected $table = 'content_revisions';

    protected $fillable = [
        'generated_content_id',
        'title',
        'content',
        'user_id',
    ];

    public $timestamps = false;

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function generatedContent(): BelongsTo
    {
        return $this->belongsTo(GeneratedContent::class, 'generated_content_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
