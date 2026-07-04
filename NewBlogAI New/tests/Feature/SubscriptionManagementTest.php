<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $supportUser;

    protected Customer $customer;

    protected Plan $starterPlan;

    protected Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@newsblogify.com',
            'password' => bcrypt('password'),
        ]);
        $this->superAdmin->role = 1;
        $this->superAdmin->save();

        // Create Support Staff
        $this->supportUser = User::create([
            'name' => 'Support Staff',
            'email' => 'support@newsblogify.com',
            'password' => bcrypt('password'),
        ]);
        $this->supportUser->role = 3;
        $this->supportUser->save();

        // Create Customer
        $this->customer = Customer::create([
            'company_name' => 'Alpha Media',
            'owner_name' => 'Bob Vance',
            'email' => 'bob@alpha.com',
            'status' => 'active',
        ]);

        // Create Starter Plan
        $this->starterPlan = Plan::create([
            'name' => 'Starter',
            'monthly_price' => 29.00,
            'yearly_price' => 290.00,
            'max_wordpress_sites' => 3,
            'max_topics' => 5,
            'publishing_schedule_limit' => 2,
            'max_articles_per_day' => 5,
            'prompt_templates_allowed' => 3,
            'ai_providers_available' => ['openai'],
            'api_keys_allowed' => 1,
            'storage_limit' => 1024,
            'status' => 'active',
        ]);

        // Create Pro Plan
        $this->proPlan = Plan::create([
            'name' => 'Professional',
            'monthly_price' => 79.00,
            'yearly_price' => 790.00,
            'max_wordpress_sites' => 10,
            'max_topics' => 20,
            'publishing_schedule_limit' => 5,
            'max_articles_per_day' => 20,
            'prompt_templates_allowed' => 10,
            'ai_providers_available' => ['openai', 'anthropic'],
            'api_keys_allowed' => 3,
            'storage_limit' => 5120,
            'status' => 'active',
        ]);
    }

    /**
     * Test: Super Admin can subscribe a customer to a plan.
     */
    public function test_super_admin_can_subscribe_customer(): void
    {
        $payload = [
            'plan_id' => $this->starterPlan->id,
            'billing_period' => 'monthly',
            'payment_token' => 'mock_token',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/customers/{$this->customer->id}/subscription", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('subscriptions', [
            'customer_id' => $this->customer->id,
            'plan_id' => $this->starterPlan->id,
        ]);

        // Assert billing history was recorded
        $this->assertDatabaseHas('subscription_histories', [
            'customer_id' => $this->customer->id,
            'event_type' => 'created',
            'amount_paid' => 29.00,
        ]);
    }

    /**
     * Test: Upgrades take effect immediately and log pro-rated charges.
     */
    public function test_customer_upgrade_lifecycle(): void
    {
        // 1. Setup active starter subscription
        $subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'plan_id' => $this->starterPlan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now(),
            'limits' => $this->starterPlan->toArray(),
        ]);

        // 2. Perform upgrade via Super Admin
        $payload = [
            'plan_id' => $this->proPlan->id,
            'billing_period' => 'monthly',
        ];

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/customers/{$this->customer->id}/subscription/upgrade", $payload);

        $response->assertStatus(200);

        // Check updated limits
        $this->assertEquals(10, $response->json('data.limits.max_wordpress_sites'));

        // Assert billing history upgrade logged
        $this->assertDatabaseHas('subscription_histories', [
            'customer_id' => $this->customer->id,
            'event_type' => 'upgraded',
            'amount_paid' => 79.00,
        ]);
    }

    /**
     * Test: Support cannot perform billing adjustments.
     */
    public function test_support_cannot_modify_subscription(): void
    {
        $payload = [
            'plan_id' => $this->starterPlan->id,
            'billing_period' => 'monthly',
        ];

        $response = $this->actingAs($this->supportUser)
            ->postJson("/api/v1/customers/{$this->customer->id}/subscription", $payload);

        $response->assertStatus(403);
    }
}
