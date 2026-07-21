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
     * @param  string  $customerEmail  Billing email address
     * @param  float  $amount  Amount in USD
     * @param  string|null  $token  Payment token (e.g., Stripe tok_xxx)
     * @return array Transaction result details
     *
     * @throws \RuntimeException If the charge fails
     */
    public function charge(string $customerEmail, float $amount, ?string $token = null): array;

    /**
     * Refund a transaction.
     *
     * @param  string  $transactionId  Gateway transaction ID to refund
     * @param  float  $amount  Amount to refund
     * @return array Refund result details
     */
    public function refund(string $transactionId, float $amount): array;

    /**
     * Cancel a recurring subscription at the gateway level.
     *
     * @param  string  $subscriptionId  Internal or gateway subscription reference
     */
    public function cancelSubscription(string $subscriptionId): bool;

    /**
     * Create a customer at the gateway.
     *
     * @param  string  $email
     * @param  string  $name
     * @return string Gateway customer ID
     */
    public function createCustomer(string $email, string $name): string;
}
