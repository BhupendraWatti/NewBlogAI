<?php

namespace App\Modules\CustomerManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Modules\CustomerManager\Observers\CustomerObserver;

class Customer extends Model
{
    use SoftDeletes, HasUuids;

    protected static function boot()
    {
        parent::boot();
        static::observe(CustomerObserver::class);
    }

    protected $table = 'customers';

    protected $fillable = [
        'company_name',
        'owner_name',
        'email',
        'phone',
        'country',
        'timezone',
        'language',
        'company_logo',
        'website',
        'industry',
        'status', // trial, active, suspended, expired, cancelled, archived
        'tags',
        'health_score',
        'last_login_at',
        'last_activity_at'
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'health_score' => 'integer',
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Relationship: Customer has many Notes.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class, 'customer_id');
    }

    /**
     * Relationship: Customer has many Activities/Audit Logs.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CustomerActivity::class, 'customer_id');
    }

    /**
     * Relationship: Customer has one Subscription.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(\App\Modules\SubscriptionManager\Models\Subscription::class, 'customer_id');
    }

    /**
     * Relationship: Customer has many WordPress Sites.
     */
    public function sites(): HasMany
    {
        return $this->hasMany(\App\Modules\SiteManager\Models\Site::class, 'customer_id');
    }
}
