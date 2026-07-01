<?php

namespace App\Modules\SubscriptionManager\Services;

use App\Modules\SubscriptionManager\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;

/**
 * PaymentGatewayStub -- Mock Adapter for the PaymentGatewayInterface.
 *
 * DESIGN RATIONALE:
 * This adapter satisfies the PaymentGatewayInterface contract for local
 * development and test environments. In production, bind a real adapter
 * (e.g., StripeGateway) in AppServiceProvider:
 *
 *     $this->app->bind(PaymentGatewayInterface::class, StripeGateway::class);
 */
class PaymentGatewayStub implements PaymentGatewayInterface
{
    public function charge(string $customerEmail, float $amount, ?string $token = null): array
    {
        Log::info("Stubbing Payment Charge", [
            'email'  => $customerEmail,
            'amount' => $amount,
            'token'  => $token ?? 'default_card'
        ]);

        usleep(100000); // 100ms

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

    public function cancelSubscription(string $subscriptionId): bool
    {
        Log::info("Stubbing gateway cancel subscription: {$subscriptionId}");
        return true;
    }
}
