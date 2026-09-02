<?php

namespace App\Modules\SubscriptionManager\Services\Adapters;

use App\Modules\SubscriptionManager\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeGatewayAdapter implements PaymentGatewayInterface
{
    protected string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret', 'sk_test_mock');
    }

    public function charge(string $customerEmail, float $amount, ?string $token = null): array
    {
        Log::info("Stripe Charge request for {$customerEmail} of amount {$amount}");

        // In a real implementation we would call Stripe API:
        // Stripe\Charge::create([...]) or Stripe\PaymentIntent::create([...])
        // Here we simulate the API call with fallbacks or mock behavior for safety:
        if ($this->secretKey === 'sk_test_mock') {
            if ($token === 'fail_token') {
                throw new \RuntimeException('Stripe Payment Declined: Insufficient Funds.');
            }
            return [
                'transaction_id' => 'ch_stripe_' . bin2hex(random_bytes(8)),
                'status' => 'succeeded',
                'amount' => $amount,
                'currency' => 'INR',
                'gateway' => 'Stripe',
            ];
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->asForm()
                ->post('https://api.stripe.com/v1/payment_intents', [
                    'amount' => (int)($amount * 100),
                    'currency' => 'inr',
                    'receipt_email' => $customerEmail,
                    'payment_method' => $token ?? 'pm_card_visa',
                    'confirm' => true,
                    'automatic_payment_methods' => [
                        'enabled' => true,
                        'allow_redirects' => 'never',
                    ],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Stripe API error: ' . ($response->json('error.message') ?? 'Unknown error'));
            }

            return [
                'transaction_id' => $response->json('id'),
                'status' => $response->json('status') === 'succeeded' ? 'succeeded' : 'failed',
                'amount' => $amount,
                'currency' => 'INR',
                'gateway' => 'Stripe',
            ];
        } catch (\Exception $e) {
            Log::error('Stripe charge exception: ' . $e->getMessage());
            throw new \RuntimeException('Stripe gateway charge failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function refund(string $transactionId, float $amount): array
    {
        Log::info("Stripe Refund request for transaction {$transactionId} of amount {$amount}");

        if ($this->secretKey === 'sk_test_mock') {
            return [
                'refund_id' => 're_stripe_' . bin2hex(random_bytes(8)),
                'status' => 'succeeded',
                'amount' => $amount,
                'currency' => 'INR',
                'gateway' => 'Stripe',
            ];
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->asForm()
                ->post('https://api.stripe.com/v1/refunds', [
                    'payment_intent' => $transactionId,
                    'amount' => (int)($amount * 100),
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Stripe refund API error: ' . ($response->json('error.message') ?? 'Unknown error'));
            }

            return [
                'refund_id' => $response->json('id'),
                'status' => 'succeeded',
                'amount' => $amount,
                'currency' => 'INR',
                'gateway' => 'Stripe',
            ];
        } catch (\Exception $e) {
            Log::error('Stripe refund exception: ' . $e->getMessage());
            throw new \RuntimeException('Stripe gateway refund failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        Log::info("Stripe cancel subscription: {$subscriptionId}");
        return true;
    }

    public function createCustomer(string $email, string $name): string
    {
        Log::info("Stripe customer creation: {$email}");

        if ($this->secretKey === 'sk_test_mock') {
            return 'cus_stripe_' . bin2hex(random_bytes(8));
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->asForm()
                ->post('https://api.stripe.com/v1/customers', [
                    'email' => $email,
                    'name' => $name,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Stripe customer creation API error: ' . ($response->json('error.message') ?? 'Unknown error'));
            }

            return $response->json('id');
        } catch (\Exception $e) {
            Log::error('Stripe customer creation exception: ' . $e->getMessage());
            throw new \RuntimeException('Stripe customer creation failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
