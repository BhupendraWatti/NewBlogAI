<?php

namespace App\Modules\SubscriptionManager\Services;

use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\Models\CustomerActivity;
use App\Modules\SubscriptionManager\Contracts\PaymentGatewayInterface;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Models\SubscriptionHistory;
use App\Modules\SubscriptionManager\Models\Invoice;
use App\Modules\SubscriptionManager\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected CouponService $couponService
    ) {}

    /**
     * Subscribe a customer to a plan.
     */
    public function subscribe(
        Customer $customer,
        Plan $plan,
        string $billingPeriod,
        ?string $paymentToken = null,
        ?string $couponCode = null,
        ?string $status = 'active'
    ): Subscription {
        // 1. Fail Fast: Check active subscriptions
        $existing = Subscription::where('customer_id', $customer->id)->first();
        if ($existing) {
            throw new \InvalidArgumentException('Customer already has an active subscription. Use upgrade or downgrade endpoints instead.');
        }

        if ($plan->status !== 'active') {
            throw new \DomainException('Cannot subscribe to an inactive plan.');
        }

        $originalPrice = $billingPeriod === 'yearly' ? (float) $plan->yearly_price : (float) $plan->monthly_price;
        $price = $originalPrice;
        $discount = 0.00;
        $coupon = null;

        if ($couponCode) {
            $coupon = $this->couponService->validateCoupon($couponCode);
            $price = $this->couponService->calculateDiscountedPrice($coupon, $originalPrice);
            $discount = $originalPrice - $price;
        }

        $subscriptionStatus = $status ?: 'active';
        $trialEndsAt = null;

        if ($subscriptionStatus === 'trial') {
            $price = 0.00;
            $discount = $originalPrice;
            $trialEndsAt = now()->addDays(14);
        }

        try {
            return DB::transaction(function () use (
                $customer,
                $plan,
                $billingPeriod,
                $originalPrice,
                $price,
                $discount,
                $coupon,
                $couponCode,
                $subscriptionStatus,
                $trialEndsAt,
                $paymentToken
            ) {
                $gatewayTxId = null;

                // 2. Charge via gateway if active and price > 0
                if ($subscriptionStatus === 'active' && $price > 0) {
                    $chargeResult = $this->gateway->charge($customer->email, $price, $paymentToken);
                    $gatewayTxId = $chargeResult['transaction_id'] ?? null;
                }

                // 3. Create active subscription and snapshot limits
                $subscription = Subscription::create([
                    'customer_id' => $customer->id,
                    'plan_id' => $plan->id,
                    'status' => $subscriptionStatus,
                    'billing_period' => $billingPeriod,
                    'starts_at' => now(),
                    'ends_at' => $subscriptionStatus === 'trial' ? null : ($billingPeriod === 'yearly' ? now()->addYear() : now()->addMonth()),
                    'trial_ends_at' => $trialEndsAt,
                    'limits' => $plan->toArray(),
                ]);

                // 4. Create Invoice
                $invoiceNumber = 'INV-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $invoice = Invoice::create([
                    'customer_id' => $customer->id,
                    'subscription_id' => $subscription->id,
                    'invoice_number' => $invoiceNumber,
                    'subtotal' => $originalPrice,
                    'discount' => $discount,
                    'total' => $price,
                    'currency' => 'INR',
                    'status' => 'paid',
                    'due_at' => now(),
                    'paid_at' => now(),
                    'billing_reason' => $subscriptionStatus === 'trial' ? 'trial_activation' : 'subscription_create',
                    'gateway_invoice_id' => $gatewayTxId,
                ]);

                // 5. Create Transaction
                Transaction::create([
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'gateway' => $couponCode ? 'coupon' : (config('services.payment_gateway', 'stub')),
                    'gateway_transaction_id' => $gatewayTxId,
                    'amount' => $price,
                    'currency' => 'INR',
                    'type' => 'charge',
                    'status' => 'succeeded',
                ]);

                // 6. Redeem Coupon if applied
                if ($couponCode && $coupon) {
                    $this->couponService->redeemCoupon($couponCode, $customer, $subscription);
                }

                // 7. Log subscription history
                SubscriptionHistory::create([
                    'customer_id' => $customer->id,
                    'plan_id' => $plan->id,
                    'event_type' => 'created',
                    'billing_period' => $billingPeriod,
                    'amount_paid' => $price,
                ]);

                // 8. Record customer activity
                CustomerActivity::create([
                    'customer_id' => $customer->id,
                    'event_type' => 'subscription_created',
                    'description' => "Subscribed to plan '{$plan->name}' ($billingPeriod).",
                    'properties' => ['plan_id' => $plan->id, 'price' => $price, 'coupon' => $couponCode],
                ]);

                return $subscription;
            });
        } catch (\Exception $e) {
            Log::error("Failed to subscribe customer {$customer->id} to plan {$plan->id}: ".$e->getMessage());
            throw new \RuntimeException('Subscription registration failed. Payment could not be charged: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Upgrade subscription immediately.
     */
    public function upgrade(
        Subscription $subscription,
        Plan $newPlan,
        string $billingPeriod,
        ?string $paymentToken = null,
        ?string $couponCode = null
    ): Subscription {
        if ($newPlan->status !== 'active') {
            throw new \DomainException('Cannot upgrade to an inactive plan.');
        }

        $originalPrice = $billingPeriod === 'yearly' ? (float) $newPlan->yearly_price : (float) $newPlan->monthly_price;
        $price = $originalPrice;
        $discount = 0.00;
        $coupon = null;

        if ($couponCode) {
            $coupon = $this->couponService->validateCoupon($couponCode);
            $price = $this->couponService->calculateDiscountedPrice($coupon, $originalPrice);
            $discount = $originalPrice - $price;
        }

        try {
            return DB::transaction(function () use (
                $subscription,
                $newPlan,
                $billingPeriod,
                $originalPrice,
                $price,
                $discount,
                $coupon,
                $couponCode,
                $paymentToken
            ) {
                $gatewayTxId = null;

                if ($price > 0) {
                    $chargeResult = $this->gateway->charge($subscription->customer->email, $price, $paymentToken);
                    $gatewayTxId = $chargeResult['transaction_id'] ?? null;
                }

                $subscription->update([
                    'plan_id' => $newPlan->id,
                    'status' => 'active',
                    'billing_period' => $billingPeriod,
                    'starts_at' => now(),
                    'ends_at' => $billingPeriod === 'yearly' ? now()->addYear() : now()->addMonth(),
                    'trial_ends_at' => null,
                    'limits' => $newPlan->toArray(),
                ]);

                // Create Invoice
                $invoiceNumber = 'INV-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $invoice = Invoice::create([
                    'customer_id' => $subscription->customer_id,
                    'subscription_id' => $subscription->id,
                    'invoice_number' => $invoiceNumber,
                    'subtotal' => $originalPrice,
                    'discount' => $discount,
                    'total' => $price,
                    'currency' => 'INR',
                    'status' => 'paid',
                    'due_at' => now(),
                    'paid_at' => now(),
                    'billing_reason' => 'subscription_update',
                    'gateway_invoice_id' => $gatewayTxId,
                ]);

                // Create Transaction
                Transaction::create([
                    'customer_id' => $subscription->customer_id,
                    'invoice_id' => $invoice->id,
                    'gateway' => $couponCode ? 'coupon' : (config('services.payment_gateway', 'stub')),
                    'gateway_transaction_id' => $gatewayTxId,
                    'amount' => $price,
                    'currency' => 'INR',
                    'type' => 'charge',
                    'status' => 'succeeded',
                ]);

                // Redeem Coupon if applied
                if ($couponCode && $coupon) {
                    $this->couponService->redeemCoupon($couponCode, $subscription->customer, $subscription);
                }

                SubscriptionHistory::create([
                    'customer_id' => $subscription->customer_id,
                    'plan_id' => $newPlan->id,
                    'event_type' => 'upgraded',
                    'billing_period' => $billingPeriod,
                    'amount_paid' => $price,
                ]);

                CustomerActivity::create([
                    'customer_id' => $subscription->customer_id,
                    'event_type' => 'subscription_upgraded',
                    'description' => "Upgraded subscription to plan '{$newPlan->name}'.",
                    'properties' => ['plan_id' => $newPlan->id, 'price' => $price, 'coupon' => $couponCode],
                ]);

                return $subscription;
            });
        } catch (\Exception $e) {
            Log::error("Failed to upgrade subscription ID {$subscription->id}: ".$e->getMessage());
            throw new \RuntimeException('Upgrade failed. Payment declined: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Downgrade subscription.
     */
    public function downgrade(Subscription $subscription, Plan $newPlan, string $billingPeriod): Subscription
    {
        if ($newPlan->status !== 'active') {
            throw new \DomainException('Cannot downgrade to an inactive plan.');
        }

        try {
            return DB::transaction(function () use ($subscription, $newPlan, $billingPeriod) {
                $subscription->update([
                    'plan_id' => $newPlan->id,
                    'billing_period' => $billingPeriod,
                    'limits' => $newPlan->toArray(),
                ]);

                SubscriptionHistory::create([
                    'customer_id' => $subscription->customer_id,
                    'plan_id' => $newPlan->id,
                    'event_type' => 'downgraded',
                    'billing_period' => $billingPeriod,
                    'amount_paid' => 0.00,
                ]);

                CustomerActivity::create([
                    'customer_id' => $subscription->customer_id,
                    'event_type' => 'subscription_downgraded',
                    'description' => "Downgraded subscription to plan '{$newPlan->name}'.",
                    'properties' => ['plan_id' => $newPlan->id],
                ]);

                return $subscription;
            });
        } catch (\Exception $e) {
            Log::error("Failed to downgrade subscription ID {$subscription->id}: ".$e->getMessage());
            throw new \RuntimeException('Downgrade failed.', 0, $e);
        }
    }

    /**
     * Renew an active or expired subscription for another billing period.
     */
    public function renew(Subscription $subscription, ?string $period = null): Subscription
    {
        $billingPeriod = $period ?: $subscription->billing_period ?: 'monthly';
        $now = now();
        $baseDate = ($subscription->ends_at && $subscription->ends_at->isFuture()) ? $subscription->ends_at->copy() : $now->copy();
        $newEndsAt = $billingPeriod === 'yearly' ? $baseDate->addYear() : $baseDate->addMonth();

        $plan = $subscription->plan;
        $price = $billingPeriod === 'yearly' ? (float) ($plan->yearly_price ?? 0) : (float) ($plan->monthly_price ?? 0);

        return DB::transaction(function () use ($subscription, $billingPeriod, $now, $newEndsAt, $plan, $price) {
            $subscription->update([
                'status' => 'active',
                'billing_period' => $billingPeriod,
                'starts_at' => $subscription->starts_at ?: $now,
                'ends_at' => $newEndsAt,
                'trial_ends_at' => null,
                'paused_at' => null,
                'cancelled_at' => null,
                'limits' => $plan ? $plan->toArray() : $subscription->limits,
            ]);

            // Create Invoice
            $invoiceNumber = 'INV-' . $now->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $invoice = Invoice::create([
                'customer_id' => $subscription->customer_id,
                'subscription_id' => $subscription->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $price,
                'discount' => 0.00,
                'total' => $price,
                'currency' => 'INR',
                'status' => 'paid',
                'due_at' => $now,
                'paid_at' => $now,
                'billing_reason' => 'subscription_cycle',
            ]);

            Transaction::create([
                'customer_id' => $subscription->customer_id,
                'invoice_id' => $invoice->id,
                'gateway' => config('services.payment_gateway', 'stub'),
                'gateway_transaction_id' => 'tx_renew_' . uniqid(),
                'amount' => $price,
                'currency' => 'INR',
                'type' => 'charge',
                'status' => 'succeeded',
            ]);

            SubscriptionHistory::create([
                'customer_id' => $subscription->customer_id,
                'plan_id' => $subscription->plan_id,
                'event_type' => 'renewed',
                'billing_period' => $billingPeriod,
                'amount_paid' => $price,
            ]);

            CustomerActivity::create([
                'customer_id' => $subscription->customer_id,
                'event_type' => 'subscription_renewed',
                'description' => "Renewed subscription for {$billingPeriod} period until {$newEndsAt->toDateString()}.",
                'properties' => ['plan_id' => $subscription->plan_id, 'price' => $price],
            ]);

            return $subscription;
        });
    }

    /**
     * Pause a subscription.
     */
    public function pause(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        CustomerActivity::create([
            'customer_id' => $subscription->customer_id,
            'event_type' => 'subscription_paused',
            'description' => 'Subscription paused.',
        ]);
    }

    /**
     * Resume a paused subscription.
     */
    public function resume(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'active',
            'paused_at' => null,
        ]);

        CustomerActivity::create([
            'customer_id' => $subscription->customer_id,
            'event_type' => 'subscription_resumed',
            'description' => 'Subscription resumed.',
        ]);
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->gateway->cancelSubscription((string) $subscription->id);

        CustomerActivity::create([
            'customer_id' => $subscription->customer_id,
            'event_type' => 'subscription_cancelled',
            'description' => 'Subscription cancelled.',
        ]);
    }
}
