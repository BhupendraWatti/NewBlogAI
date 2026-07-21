<?php

namespace App\Modules\SubscriptionManager\Services;

use App\Modules\CustomerManager\Models\Customer;
use App\Modules\SubscriptionManager\Models\Coupon;
use App\Modules\SubscriptionManager\Models\CouponRedemption;
use App\Modules\SubscriptionManager\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * Find and validate a coupon code.
     *
     * @throws \InvalidArgumentException if coupon is invalid or expired.
     */
    public function validateCoupon(string $code): Coupon
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (! $coupon) {
            throw new \InvalidArgumentException("Coupon code '{$code}' does not exist.");
        }

        if (! $coupon->isValid()) {
            throw new \InvalidArgumentException("Coupon code '{$code}' has expired or reached its utilization limit.");
        }

        return $coupon;
    }

    /**
     * Redeem a coupon for a customer and subscription.
     */
    public function redeemCoupon(string $code, Customer $customer, ?Subscription $subscription = null): CouponRedemption
    {
        return DB::transaction(function () use ($code, $customer, $subscription) {
            $coupon = $this->validateCoupon($code);

            // Increment count
            $coupon->increment('redeemed_count');

            // Log redemption
            return CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'customer_id' => $customer->id,
                'subscription_id' => $subscription?->id,
                'redeemed_at' => now(),
            ]);
        });
    }

    /**
     * Calculate discounted price based on coupon.
     */
    public function calculateDiscountedPrice(Coupon $coupon, float $originalPrice): float
    {
        if ($coupon->type === 'percentage') {
            $discount = ($originalPrice * $coupon->value) / 100;
        } else {
            $discount = $coupon->value;
        }

        return max(0.00, $originalPrice - $discount);
    }
}
