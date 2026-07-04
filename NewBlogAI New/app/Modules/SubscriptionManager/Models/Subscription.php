<?php

namespace App\Modules\SubscriptionManager\Models;

use App\Modules\TopicManager\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'customer_id',
        'plan_id',
        'status', // trial, active, paused, expired, cancelled, pending_payment, suspended, archived
        'billing_period', // monthly, yearly
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'paused_at',
        'cancelled_at',
        'limits', // JSON snapshot
    ];

    protected $casts = [
        'limits' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'paused_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Relationship: Subscription belongs to a Customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo('App\Modules\CustomerManager\Models\Customer', 'customer_id');
    }

    /**
     * Relationship: Subscription belongs to a Plan.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function topics()
    {
        return $this->hasMany(Topic::class, 'subscription_id');
    }
}
