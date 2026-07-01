<?php

namespace App\Modules\SubscriptionManager\Services;

use Illuminate\Support\Facades\Log;

/**
 * Payment Gateway Stub (Mock Adapter for Billing APIs)
 * 
 * DESIGN RATIONALE:
 * This class abstracts the payment processing boundary (e.g. Stripe, PayPal, or Chargebee).
 * In a real production deployment, this would use the payment provider SDKs to validate
 * tokens, create customer profiles, charge cards, and handle asynchronous webhook states.
 */
class PaymentGatewayStub
{
    /**
     * Charge a customer card.
     *
     * @param string $customerEmail Target billing email
     * @param float $amount Price in USD
     * @param string|null $token Payment token (e.g., Stripe tok_123)
     * @return array Transaction details
     * @throws \RuntimeException If payment fails
     */
    public static function charge(string $customerEmail, float $amount, ?string $token = null): array
    {
        Log::info("Stubbing Payment Charge", [
            'email'  => $customerEmail,
            'amount' => $amount,
            'token'  => $token ?? 'default_card'
        ]);

        // Simulating standard Stripe API response delay
        usleep(100000); // 100ms

        // Simulate random payment failure cases
        if ($token === 'fail_token') {
            throw new \RuntimeException("Payment Declined: Insufficient Funds.");
        }

        return [
            'transaction_id' => 'ch_' . bin2hex(random_bytes(8)),
            'status'         => 'succeeded',
            'amount'         => $amount,
            'currency'       => 'usd',
            'gateway'        => 'Stripe Mock Core'
        ];
    }

    /**
     * Cancel recurring portal billing.
     *
     * @param string $subscriptionId Gateway subscription ID reference
     * @return bool
     */
    public static function cancelSubscription(string $subscriptionId): bool
    {
        Log::info("Stubbing gateway cancel subscription: {$subscriptionId}");
        return true;
    }
}
