<?php

namespace App\Modules\Licensing\Models;

use App\Modules\CustomerManager\Models\Customer;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginLicense extends Model
{
    protected $table = 'plugin_licenses';

    protected $fillable = [
        'license_key',
        'customer_id',
        'site_id',
        'domain',
        'status', // active, inactive, expired, revoked
        'installations_count',
        'max_installations',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * Check if the license is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
