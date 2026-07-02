<?php

namespace App\Modules\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Cast the value to its appropriate format.
     */
    protected function casts(): array
    {
        return [
            // settings can store JSON or raw strings
            'value' => 'array',
        ];
    }
}
