<?php

namespace App\Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleLog extends Model
{
    protected $table = 'schedule_logs';

    protected $fillable = [
        'task_name',
        'status', // running, success, failed
        'output',
        'started_at',
        'completed_at',
    ];

    public $timestamps = false;

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
