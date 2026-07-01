<?php

namespace App\Modules\PromptManager\Models;

use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    /**
     * The table is named 'promts' for backward compatibility
     * with the existing migration. A future migration can rename it to 'prompts'.
     */
    protected $table = 'promts';

    protected $fillable = ['name', 'promt'];
}
