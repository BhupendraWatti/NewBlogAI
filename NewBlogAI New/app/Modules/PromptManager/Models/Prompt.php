<?php

namespace App\Modules\PromptManager\Models;

use App\Modules\TopicManager\Models\Topic;
use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'prompts';

    protected $fillable = [
        'name',
        'topic_id',
        'prompt', // the raw prompt text
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
        return $this->belongsTo(Topic::class, 'topic_id');
    }
}
