<?php

namespace App\Modules\SubscriptionManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionHistory extends Model
{
    protected $table = 'subscription_histories';

    public $timestamps = false; // created_at only

    protected $fillable = [
        'customer_id',
        'plan_id',
        'event_type', // created, upgraded, downgraded, cancelled, renewed
        'billing_period',
        'amount_paid',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /**
     * Relationship: History belongs to a Customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo('App\Modules\CustomerManager\Models\Customer', 'customer_id');
    }

    /**
     * Relationship: History belongs to a Plan.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
