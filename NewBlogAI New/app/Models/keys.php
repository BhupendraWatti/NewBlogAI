<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class keys extends Model
{
    protected $table = 'keys';
    protected $fillable = [
        'name',
        'key',
        'user_id',
        'key_hash',
        'abilities',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = ['key', 'key_hash'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
