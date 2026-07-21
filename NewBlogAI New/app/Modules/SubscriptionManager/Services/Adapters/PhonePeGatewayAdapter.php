<?php

namespace App\Modules\SubscriptionManager\Services\Adapters;

use App\Modules\SubscriptionManager\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;

class PhonePeGatewayAdapter implements PaymentGatewayInterface
{
    protected string $merchantId;

    public function __construct()
    {
        $this->merchantId = config('services.phonepe.merchant_id', 'MID_MOCK');
    }

    public function charge(string $customerEmail, float $amount, ?string $token = null): array
    {
        Log::info("PhonePe Charge request for {$customerEmail} of amount {$amount}");

        if ($token === 'fail_token') {
            throw new \RuntimeException('PhonePe Payment Declined.');
        }

        return [
            'transaction_id' => 'tx_phonepe_' . bin2hex(random_bytes(8)),
            'status' => 'succeeded',
            'amount' => $amount,
            'currency' => 'INR',
            'gateway' => 'PhonePe',
        ];
    }

    public function refund(string $transactionId, float $amount): array
    {
        Log::info("PhonePe Refund request for payment {$transactionId} of amount {$amount}");

        return [
            'refund_id' => 're_phonepe_' . bin2hex(random_bytes(8)),
            'status' => 'succeeded',
            'amount' => $amount,
            'currency' => 'INR',
            'gateway' => 'PhonePe',
        ];
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        Log::info("PhonePe cancel subscription: {$subscriptionId}");
        return true;
    }

    public function createCustomer(string $email, string $name): string
    {
        Log::info("PhonePe customer creation: {$email}");
        return 'cus_phonepe_' . bin2hex(random_bytes(8));
    }
}
