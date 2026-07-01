<?php

namespace App\Modules\SiteManager\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Promt;

class Site extends Model
{
    protected $fillable = [
        'domain_url',
        'api_key',
        'key_id',
        'selected_topics',
        'promt_id',
        'slot',
        'is_active',
        'last_synced_at',
        'last_sync_status',
        'error_log'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'selected_topics' => 'array',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function promt()
    {
        return $this->belongsTo(Promt::class, 'promt_id');
    }
}
