<?php

namespace App\Modules\PromptManager\Models;

use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    /**
     * The table is named 'promts' for backward compatibility.
     */
    protected $table = 'promts';

    protected $fillable = [
        'name',
        'topic_id',
        'promt', // the raw prompt text
        'category',
        'variables', // json list of variables, e.g. ["topic", "keyword", "tone"]
        'version',
        'status', // active, inactive
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }

    public function topic()
    {
        return $this->belongsTo(\App\Modules\TopicManager\Models\Topic::class, 'topic_id');
    }
}
