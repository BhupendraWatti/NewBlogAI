<?php

namespace App\Modules\SubscriptionManager\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'coupons';

    protected $fillable = [
        'code',
        'type', // percentage, fixed
        'value',
        'duration', // once, repeating, forever
        'duration_in_months',
        'max_redemptions',
        'redeemed_count',
        'expires_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'expires_at' => 'datetime',
        'redeemed_count' => 'integer',
        'max_redemptions' => 'integer',
        'duration_in_months' => 'integer',
    ];

    /**
     * Relationship: Coupon has many Redemptions.
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'coupon_id');
    }

    /**
     * Check if the coupon is valid and can be redeemed.
     */
    public function isValid(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->redeemed_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }
}
