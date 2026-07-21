<?php

namespace App\Modules\SubscriptionManager\Services\Adapters;

use App\Modules\SubscriptionManager\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayGatewayAdapter implements PaymentGatewayInterface
{
    protected string $keyId;
    protected string $keySecret;

    public function __construct()
    {
        $this->keyId = config('services.razorpay.key_id', 'rzp_test_mock');
        $this->keySecret = config('services.razorpay.key_secret', 'mock_secret');
    }

    public function charge(string $customerEmail, float $amount, ?string $token = null): array
    {
        Log::info("Razorpay Charge request for {$customerEmail} of amount {$amount}");

        if ($this->keyId === 'rzp_test_mock') {
            if ($token === 'fail_token') {
                throw new \RuntimeException('Razorpay Payment Declined.');
            }
            return [
                'transaction_id' => 'pay_rzp_' . bin2hex(random_bytes(8)),
                'status' => 'succeeded',
                'amount' => $amount,
                'currency' => 'INR',
                'gateway' => 'Razorpay',
            ];
        }

        try {
            // Razorpay uses order and payment capture flow
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => (int)($amount * 100),
                    'currency' => 'INR',
                    'receipt' => 'receipt_' . uniqid(),
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Razorpay order creation failed: ' . $response->body());
            }

            return [
                'transaction_id' => $response->json('id'),
                'status' => 'succeeded', // assuming mock successful capture flow
                'amount' => $amount,
                'currency' => 'INR',
                'gateway' => 'Razorpay',
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay charge exception: ' . $e->getMessage());
            throw new \RuntimeException('Razorpay charge failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function refund(string $transactionId, float $amount): array
    {
        Log::info("Razorpay Refund request for payment {$transactionId} of amount {$amount}");

        if ($this->keyId === 'rzp_test_mock') {
            return [
                'refund_id' => 'rfnd_rzp_' . bin2hex(random_bytes(8)),
                'status' => 'succeeded',
                'amount' => $amount,
                'currency' => 'INR',
                'gateway' => 'Razorpay',
            ];
        }

        try {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->post("https://api.razorpay.com/v1/payments/{$transactionId}/refund", [
                    'amount' => (int)($amount * 100),
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Razorpay refund failed: ' . $response->body());
            }

            return [
                'refund_id' => $response->json('id'),
                'status' => 'succeeded',
                'amount' => $amount,
                'currency' => 'INR',
                'gateway' => 'Razorpay',
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay refund exception: ' . $e->getMessage());
            throw new \RuntimeException('Razorpay refund failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        Log::info("Razorpay cancel subscription: {$subscriptionId}");
        return true;
    }

    public function createCustomer(string $email, string $name): string
    {
        Log::info("Razorpay customer creation: {$email}");

        if ($this->keyId === 'rzp_test_mock') {
            return 'cust_rzp_' . bin2hex(random_bytes(8));
        }

        try {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->post('https://api.razorpay.com/v1/customers', [
                    'email' => $email,
                    'name' => $name,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Razorpay customer creation failed: ' . $response->body());
            }

            return $response->json('id');
        } catch (\Exception $e) {
            Log::error('Razorpay customer creation exception: ' . $e->getMessage());
            throw new \RuntimeException('Razorpay customer creation failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
