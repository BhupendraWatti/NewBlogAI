<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\SubscriptionManager\Models\Coupon;
use App\Modules\SubscriptionManager\Models\Invoice;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Models\Transaction;
use App\Modules\SubscriptionManager\Services\CouponService;
use App\Modules\SubscriptionManager\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BillingCouponAndInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Operator',
            'email' => 'admin@newsblogify.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->role = 1;
        $this->admin->save();

        $this->customer = Customer::create([
            'company_name' => 'Beta Media Group',
            'owner_name' => 'Alice Vance',
            'email' => 'alice@beta.com',
            'status' => 'active',
        ]);

        $this->plan = Plan::create([
            'name' => 'Premium Plus',
            'monthly_price' => 100.00,
            'yearly_price' => 1000.00,
            'max_wordpress_sites' => 5,
            'max_topics' => 10,
            'publishing_schedule_limit' => 5,
            'max_articles_per_day' => 10,
            'prompt_templates_allowed' => 5,
            'ai_providers_available' => ['openai', 'gemini'],
            'api_keys_allowed' => 2,
            'storage_limit' => 2048,
            'status' => 'active',
        ]);
    }

    /**
     * Test: Validate coupon validation and discount calculations.
     */
    public function test_coupon_validation_and_discount_calculation(): void
    {
        $percentageCoupon = Coupon::create([
            'code' => 'DISCOUNT20',
            'type' => 'percentage',
            'value' => 20.00,
            'duration' => 'once',
            'max_redemptions' => 10,
            'redeemed_count' => 0,
        ]);

        $fixedCoupon = Coupon::create([
            'code' => 'SAVE15',
            'type' => 'fixed',
            'value' => 15.00,
            'duration' => 'once',
            'max_redemptions' => 5,
            'redeemed_count' => 0,
        ]);

        $service = app(CouponService::class);

        $validatedPercentage = $service->validateCoupon('DISCOUNT20');
        $this->assertEquals($percentageCoupon->id, $validatedPercentage->id);

        $price1 = $service->calculateDiscountedPrice($percentageCoupon, 100.00);
        $this->assertEquals(80.00, $price1);

        $price2 = $service->calculateDiscountedPrice($fixedCoupon, 100.00);
        $this->assertEquals(85.00, $price2);
    }

    /**
     * Test: Subscribing with coupon logs redemption, invoice and transaction.
     */
    public function test_subscription_with_coupon_generates_invoice_and_transaction(): void
    {
        Coupon::create([
            'code' => 'WELCOME50',
            'type' => 'percentage',
            'value' => 50.00,
            'duration' => 'once',
            'max_redemptions' => 100,
            'redeemed_count' => 0,
        ]);

        $payload = [
            'plan_id' => $this->plan->id,
            'billing_period' => 'monthly',
            'payment_token' => 'mock_token',
            'coupon_code' => 'WELCOME50',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/customers/{$this->customer->id}/subscription", $payload);

        $response->assertStatus(201);

        // Verify coupon redemption
        $this->assertDatabaseHas('coupon_redemptions', [
            'customer_id' => $this->customer->id,
        ]);

        // Verify invoice details
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'subtotal' => 100.00,
            'discount' => 50.00,
            'total' => 50.00,
            'status' => 'paid',
        ]);

        // Verify transaction logged
        $this->assertDatabaseHas('transactions', [
            'customer_id' => $this->customer->id,
            'amount' => 50.00,
            'status' => 'succeeded',
        ]);
    }

    /**
     * Test: Auto renewal lifecycle attempts to charge gateway and extends ends_at.
     */
    public function test_subscription_auto_renewal_lifecycle(): void
    {
        $now = now()->startOfDay();
        $this->travelTo($now);

        // 1. Create a subscription that is active but expired
        $subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => $now->copy()->subMonth(),
            'ends_at' => $now->copy()->subDay(),
            'limits' => $this->plan->toArray(),
        ]);

        // 2. Trigger the scheduler lifecycle command
        Artisan::call('schedule:run');

        // 3. Verify subscription ends_at was updated/extended
        $subscription->refresh();
        $this->assertTrue($subscription->ends_at->isAfter($now));
        $this->assertEquals('active', $subscription->status);

        // 4. Verify renewal invoice and transaction exist
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'total' => 100.00,
            'billing_reason' => 'subscription_cycle',
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('transactions', [
            'customer_id' => $this->customer->id,
            'amount' => 100.00,
            'status' => 'succeeded',
        ]);
    }

    /**
     * Test: Pipeline run stores user_id and propagates to content and log.
     */
    public function test_pipeline_run_stores_user_id_and_propagates_to_content(): void
    {
        $site = \App\Modules\SiteManager\Models\Site::create([
            'customer_id' => $this->customer->id,
            'domain_url' => 'https://example.com',
            'api_key' => 'token',
            'is_active' => true,
        ]);

        $topic = \App\Modules\TopicManager\Models\Topic::create([
            'name' => 'Laravel Security',
        ]);

        $prompt = \App\Modules\PromptManager\Models\Prompt::create([
            'name' => 'Security Prompt',
            'prompt' => 'Write about security.',
            'category' => 'Tech',
            'status' => 'active',
            'topic_id' => $topic->id,
        ]);

        $provider = \App\Modules\AIProviderManager\Models\AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'test-key',
            'default_model' => 'gpt-4',
            'is_enabled' => true,
        ]);

        $pipeline = \App\Modules\ContentPipeline\Models\ContentPipeline::create([
            'site_id' => $site->id,
            'topic_id' => $topic->id,
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $provider->id,
            'language' => 'en',
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        // Create subscription to pass entitlements check
        Subscription::create([
            'customer_id' => $this->customer->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'limits' => $this->plan->toArray(),
        ]);

        $this->actingAs($this->admin);

        \Illuminate\Support\Facades\Queue::fake();

        $pipelineService = app(\App\Modules\ContentPipeline\Services\PipelineService::class);
        $run = $pipelineService->triggerRun($pipeline);

        $this->assertEquals($this->admin->id, $run->user_id);

        // Directly call PublishingQueueService with a context referencing the run to test propagation
        $context = new \App\Modules\ContentPipeline\DTOs\PipelineContext($run, $pipeline);
        $context->generatedContent = 'Sample generated text';
        $context->title = 'Sample Security Article';

        $queueService = app(\App\Modules\Publishing\Services\PublishingQueueService::class);
        $queueService->handle($context);

        $this->assertDatabaseHas('generated_contents', [
            'title' => 'Sample Security Article',
            'user_id' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('ai_request_logs', [
            'user_id' => $this->admin->id,
        ]);
    }
}
