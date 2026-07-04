<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;

class JobLog extends Model
{
    protected $table = 'job_logs';

    protected $fillable = [
        'job_id',
        'name',
        'queue',
        'status', // pending, processing, completed, failed
        'attempts',
        'exception',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
