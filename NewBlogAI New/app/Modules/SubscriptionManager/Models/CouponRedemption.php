<?php

namespace App\Modules\SubscriptionManager\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    use HasUuids;

    protected $table = 'coupon_redemptions';

    public $timestamps = false;

    protected $fillable = [
        'coupon_id',
        'customer_id',
        'subscription_id',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    /**
     * Relationship: Redemption belongs to Coupon.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    /**
     * Relationship: Redemption belongs to Customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo('App\Modules\CustomerManager\Models\Customer', 'customer_id');
    }

    /**
     * Relationship: Redemption belongs to Subscription.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }
}
