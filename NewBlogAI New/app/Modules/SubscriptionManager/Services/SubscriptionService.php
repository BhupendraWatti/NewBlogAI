<?php

namespace App\Modules\SubscriptionManager\Services;

use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\Models\CustomerActivity;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Models\SubscriptionHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Subscribe a customer to a plan.
     */
    public function subscribe(Customer $customer, Plan $plan, string $billingPeriod, ?string $paymentToken = null): Subscription
    {
        // 1. Fail Fast: Check active subscriptions
        $existing = Subscription::where('customer_id', $customer->id)->first();
        if ($existing) {
            throw new \InvalidArgumentException("Customer already has an active subscription. Use upgrade or downgrade endpoints instead.");
        }

        if ($plan->status !== 'active') {
            throw new \DomainException("Cannot subscribe to an inactive plan.");
        }

        $price = $billingPeriod === 'yearly' ? $plan->yearly_price : $plan->monthly_price;

        try {
            return DB::transaction(function () use ($customer, $plan, $billingPeriod, $price, $paymentToken) {
                // 2. Charge payment stub
                PaymentGatewayStub::charge($customer->email, $price, $paymentToken);

                // 3. Create active subscription and snapshot limits
                $subscription = Subscription::create([
                    'customer_id'    => $customer->id,
                    'plan_id'        => $plan->id,
                    'status'         => 'active',
                    'billing_period' => $billingPeriod,
                    'starts_at'      => now(),
                    'ends_at'        => $billingPeriod === 'yearly' ? now()->addYear() : now()->addMonth(),
                    'limits'         => $plan->toArray() // Deep Snapshot
                ]);

                // 4. Log history
                SubscriptionHistory::create([
                    'customer_id'    => $customer->id,
                    'plan_id'        => $plan->id,
                    'event_type'     => 'created',
                    'billing_period' => $billingPeriod,
                    'amount_paid'    => $price
                ]);

                CustomerActivity::create([
                    'customer_id' => $customer->id,
                    'event_type'  => 'subscription_created',
                    'description' => "Subscribed to plan '{$plan->name}' ($billingPeriod).",
                    'properties'  => ['plan_id' => $plan->id, 'price' => $price]
                ]);

                return $subscription;
            });
        } catch (\Exception $e) {
            Log::error("Failed to subscribe customer {$customer->id} to plan {$plan->id}: " . $e->getMessage());
            throw new \RuntimeException("Subscription registration failed. Payment could not be charged: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Upgrade subscription immediately.
     */
    public function upgrade(Subscription $subscription, Plan $newPlan, string $billingPeriod, ?string $paymentToken = null): Subscription
    {
        if ($newPlan->status !== 'active') {
            throw new \DomainException("Cannot upgrade to an inactive plan.");
        }

        $price = $billingPeriod === 'yearly' ? $newPlan->yearly_price : $newPlan->monthly_price;

        try {
            return DB::transaction(function () use ($subscription, $newPlan, $billingPeriod, $price, $paymentToken) {
                // Charge full price for upgrade (prorated calculation stubbed here)
                PaymentGatewayStub::charge($subscription->customer->email, $price, $paymentToken);

                $subscription->update([
                    'plan_id'        => $newPlan->id,
                    'status'         => 'active',
                    'billing_period' => $billingPeriod,
                    'starts_at'      => now(),
                    'ends_at'        => $billingPeriod === 'yearly' ? now()->addYear() : now()->addMonth(),
                    'limits'         => $newPlan->toArray() // Updated Snapshot
                ]);

                SubscriptionHistory::create([
                    'customer_id'    => $subscription->customer_id,
                    'plan_id'        => $newPlan->id,
                    'event_type'     => 'upgraded',
                    'billing_period' => $billingPeriod,
                    'amount_paid'    => $price
                ]);

                CustomerActivity::create([
                    'customer_id' => $subscription->customer_id,
                    'event_type'  => 'subscription_upgraded',
                    'description' => "Upgraded subscription to plan '{$newPlan->name}'.",
                    'properties'  => ['plan_id' => $newPlan->id, 'price' => $price]
                ]);

                return $subscription;
            });
        } catch (\Exception $e) {
            Log::error("Failed to upgrade subscription ID {$subscription->id}: " . $e->getMessage());
            throw new \RuntimeException("Upgrade failed. Payment declined: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Downgrade subscription.
     */
    public function downgrade(Subscription $subscription, Plan $newPlan, string $billingPeriod): Subscription
    {
        if ($newPlan->status !== 'active') {
            throw new \DomainException("Cannot downgrade to an inactive plan.");
        }

        try {
            return DB::transaction(function () use ($subscription, $newPlan, $billingPeriod) {
                // Downgrade applies immediately to limits
                $subscription->update([
                    'plan_id'        => $newPlan->id,
                    'billing_period' => $billingPeriod,
                    'limits'         => $newPlan->toArray()
                ]);

                SubscriptionHistory::create([
                    'customer_id'    => $subscription->customer_id,
                    'plan_id'        => $newPlan->id,
                    'event_type'     => 'downgraded',
                    'billing_period' => $billingPeriod,
                    'amount_paid'    => 0.00 // No charge for downgrades
                ]);

                CustomerActivity::create([
                    'customer_id' => $subscription->customer_id,
                    'event_type'  => 'subscription_downgraded',
                    'description' => "Downgraded subscription to plan '{$newPlan->name}'.",
                    'properties'  => ['plan_id' => $newPlan->id]
                ]);

                return $subscription;
            });
        } catch (\Exception $e) {
            Log::error("Failed to downgrade subscription ID {$subscription->id}: " . $e->getMessage());
            throw new \RuntimeException("Downgrade failed.", 0, $e);
        }
    }

    /**
     * Pause a subscription.
     */
    public function pause(Subscription $subscription): void
    {
        $subscription->update([
            'status'    => 'paused',
            'paused_at' => now()
        ]);

        CustomerActivity::create([
            'customer_id' => $subscription->customer_id,
            'event_type'  => 'subscription_paused',
            'description' => 'Subscription paused.'
        ]);
    }

    /**
     * Resume a paused subscription.
     */
    public function resume(Subscription $subscription): void
    {
        $subscription->update([
            'status'    => 'active',
            'paused_at' => null
        ]);

        CustomerActivity::create([
            'customer_id' => $subscription->customer_id,
            'event_type'  => 'subscription_resumed',
            'description' => 'Subscription resumed.'
        ]);
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Subscription $subscription): void
    {
        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now()
        ]);

        PaymentGatewayStub::cancelSubscription($subscription->id);

        CustomerActivity::create([
            'customer_id' => $subscription->customer_id,
            'event_type'  => 'subscription_cancelled',
            'description' => 'Subscription cancelled.'
        ]);
    }
}
