<?php

namespace Tests\Unit;

use App\Modules\SubscriptionManager\Services\Adapters\StripeGatewayAdapter;
use App\Modules\SubscriptionManager\Services\PaymentGatewayStub;
use Tests\TestCase;

class RupeeCurrencyTest extends TestCase
{
    public function test_cost_presentation_and_payment_gateways_use_rupees(): void
    {
        $scripts = file_get_contents(resource_path('views/partials/scripts.blade.php'));

        $this->assertStringContainsString("currency: 'INR'", $scripts);
        $this->assertStringContainsString('formatUsdAsInr(costUsd, 4)', $scripts);
        $this->assertStringNotContainsString('Recorded Est. (USD)', $scripts);
        $this->assertSame('INR', (new PaymentGatewayStub)->charge('test@example.com', 100)['currency']);

        config(['services.stripe.secret' => 'sk_test_mock']);
        $this->assertSame('INR', (new StripeGatewayAdapter)->charge('test@example.com', 100)['currency']);
    }
}
