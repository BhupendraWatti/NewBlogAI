<?php

namespace App\Modules\AuthManager\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthActivity extends Model
{
    protected $table = 'auth_activities';

    protected $fillable = [
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'description',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public $timestamps = false;

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
