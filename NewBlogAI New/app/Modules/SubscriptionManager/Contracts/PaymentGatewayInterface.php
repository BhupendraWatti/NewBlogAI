<?php

namespace App\Modules\SubscriptionManager\Contracts;

/**
 * PaymentGatewayInterface — seam between subscription lifecycle and payment processing.
 *
 * Any payment provider (Stripe, PayPal, Chargebee) implements this interface.
 * The stub adapter is used in tests and local environments.
 */
interface PaymentGatewayInterface
{
    /**
     * Charge a customer.
     *
     * @param  string      $customerEmail  Billing email address
     * @param  float       $amount         Amount in USD
     * @param  string|null $token          Payment token (e.g., Stripe tok_xxx)
     * @return array       Transaction result details
     *
     * @throws \RuntimeException If the charge fails
     */
    public function charge(string $customerEmail, float $amount, ?string $token = null): array;

    /**
     * Cancel a recurring subscription at the gateway level.
     *
     * @param  string $subscriptionId  Internal or gateway subscription reference
     * @return bool
     */
    public function cancelSubscription(string $subscriptionId): bool;
}
