<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Prompt $prompt;

    protected \App\Modules\CustomerManager\Models\Customer $customer;
    protected \App\Modules\SubscriptionManager\Models\Subscription $subscription;
    protected \App\Modules\SubscriptionManager\Models\Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = \App\Modules\CustomerManager\Models\Customer::create([
            'company_name' => 'Alpha Media Corp',
            'owner_name' => 'Bob Vance',
            'email' => 'bob@alpha.com',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'customer_id' => $this->customer->id,
        ]);
        $this->admin->role = 2; // Admin
        $this->admin->save();

        $this->plan = \App\Modules\SubscriptionManager\Models\Plan::create([
            'name' => 'Pro Plan',
            'monthly_price' => 79.00,
            'yearly_price' => 790.00,
            'max_wordpress_sites' => 3,
            'max_topics' => 10,
            'publishing_schedule_limit' => 5,
            'max_articles_per_day' => 2,
            'monthly_generation_limit' => 5,
            'prompt_templates_allowed' => 5,
            'ai_providers_available' => ['openai', 'gemini'],
            'api_keys_allowed' => 2,
            'storage_limit' => 5000,
            'feature_flags' => ['localization' => true, 'advanced_seo' => true],
            'status' => 'active',
        ]);

        $this->subscription = \App\Modules\SubscriptionManager\Models\Subscription::create([
            'customer_id' => $this->customer->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
            'limits' => $this->plan->toArray(),
        ]);

        $this->prompt = Prompt::create([
            'name' => 'Test Prompt',
            'prompt' => 'Generate text.',
            'category' => 'Testing',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_topic_with_prompt(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/topics', [
                'name' => 'Quantum Physics',
                'category' => 'Science',
                'priority' => 'high',
                'language' => 'en',
                'status' => 'active',
                'generation_frequency' => 'daily',
                'tags' => ['physics', 'quantum'],
                'prompt_id' => $this->prompt->id,
                'subscription_id' => $this->subscription->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Quantum Physics')
            ->assertJsonPath('data.prompt_id', $this->prompt->id);

        $this->assertDatabaseHas('topics', [
            'name' => 'Quantum Physics',
            'prompt_id' => $this->prompt->id,
            'subscription_id' => $this->subscription->id,
        ]);
    }

    public function test_duplicate_prevention_same_category(): void
    {
        // First creation
        Topic::create([
            'name' => 'SEO Writing',
            'category' => 'Marketing',
            'priority' => 'medium',
            'language' => 'en',
            'status' => 'active',
            'generation_frequency' => 'weekly',
            'subscription_id' => $this->subscription->id,
        ]);

        // Attempting to create duplicate
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/topics', [
                'name' => 'SEO Writing',
                'category' => 'Marketing',
                'priority' => 'medium',
                'language' => 'en',
                'status' => 'active',
                'generation_frequency' => 'weekly',
                'subscription_id' => $this->subscription->id,
            ]);

        $response->assertStatus(500); // Throws runtime/duplicate exception
    }

    public function test_soft_delete_and_restore_lifecycle(): void
    {
        $topic = Topic::create([
            'name' => 'Deleteme',
            'category' => 'Trash',
            'priority' => 'low',
            'language' => 'en',
            'status' => 'draft',
            'generation_frequency' => 'monthly',
            'subscription_id' => $this->subscription->id,
        ]);

        // Delete
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/topics/{$topic->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('topics', ['id' => $topic->id]);

        // Restore
        $restoreResponse = $this->actingAs($this->admin)
            ->postJson("/api/v1/topics/{$topic->id}/restore");

        $restoreResponse->assertStatus(200);
        $this->assertDatabaseHas('topics', [
            'id' => $topic->id,
            'deleted_at' => null,
        ]);
    }
}
