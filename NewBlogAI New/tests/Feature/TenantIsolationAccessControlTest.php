<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\Publishing\Models\PublishingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customerA;
    protected Customer $customerB;
    protected User $tenantAdminA;
    protected User $tenantAdminB;
    protected User $superAdmin;
    protected Subscription $subscriptionA;
    protected Subscription $subscriptionB;
    protected Site $siteA;
    protected Site $siteB;
    protected Topic $topicA;
    protected Topic $topicB;
    protected Prompt $promptA;
    protected Prompt $promptB;
    protected AIProvider $provider;
    protected ContentPipeline $pipelineA;
    protected ContentPipeline $pipelineB;
    protected PipelineRun $runA;
    protected PipelineRun $runB;
    protected GeneratedContent $contentA;
    protected GeneratedContent $contentB;
    protected PublishingLog $publishingLogA;
    protected PublishingLog $publishingLogB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Customers
        $this->customerA = Customer::create([
            'company_name' => 'Company A',
            'owner_name' => 'Owner A',
            'email' => 'ownerA@company.com',
            'status' => 'active',
        ]);
        $this->customerB = Customer::create([
            'company_name' => 'Company B',
            'owner_name' => 'Owner B',
            'email' => 'ownerB@company.com',
            'status' => 'active',
        ]);

        // Create Users
        $this->tenantAdminA = User::create([
            'name' => 'Admin A',
            'email' => 'adminA@company.com',
            'password' => bcrypt('password'),
            'customer_id' => $this->customerA->id,
        ]);
        $this->tenantAdminA->role = 2;
        $this->tenantAdminA->save();

        $this->tenantAdminB = User::create([
            'name' => 'Admin B',
            'email' => 'adminB@company.com',
            'password' => bcrypt('password'),
            'customer_id' => $this->customerB->id,
        ]);
        $this->tenantAdminB->role = 2;
        $this->tenantAdminB->save();

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@company.com',
            'password' => bcrypt('password'),
        ]);
        $this->superAdmin->role = 1;
        $this->superAdmin->save();

        // Create plan
        $plan = Plan::create([
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
            'feature_flags' => ['localization' => true],
            'status' => 'active',
        ]);

        // Create Subscriptions
        $this->subscriptionA = Subscription::create([
            'customer_id' => $this->customerA->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
            'limits' => $plan->toArray(),
        ]);
        $this->subscriptionB = Subscription::create([
            'customer_id' => $this->customerB->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
            'limits' => $plan->toArray(),
        ]);

        // Create Sites
        $this->siteA = Site::create([
            'customer_id' => $this->customerA->id,
            'domain_url' => 'https://siteA.com',
            'api_key' => 'tokenA',
            'is_active' => true,
        ]);
        $this->siteB = Site::create([
            'customer_id' => $this->customerB->id,
            'domain_url' => 'https://siteB.com',
            'api_key' => 'tokenB',
            'is_active' => true,
        ]);

        // Create Topics
        $this->topicA = Topic::create([
            'name' => 'Topic A',
            'category' => 'Tech',
            'status' => 'active',
            'subscription_id' => $this->subscriptionA->id,
        ]);
        $this->topicB = Topic::create([
            'name' => 'Topic B',
            'category' => 'Tech',
            'status' => 'active',
            'subscription_id' => $this->subscriptionB->id,
        ]);

        // Create Prompts
        $this->promptA = Prompt::create([
            'name' => 'Prompt A',
            'prompt' => 'Write A',
            'category' => 'Tech',
            'status' => 'active',
        ]);
        $this->promptB = Prompt::create([
            'name' => 'Prompt B',
            'prompt' => 'Write B',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        // Create AI Provider
        $this->provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Google Gemini',
            'api_key' => 'key',
            'default_model' => 'gemini-2.5-flash',
            'is_enabled' => true,
        ]);

        // Create Pipelines
        $this->pipelineA = ContentPipeline::create([
            'site_id' => $this->siteA->id,
            'topic_id' => $this->topicA->id,
            'prompt_id' => $this->promptA->id,
            'ai_provider_id' => $this->provider->id,
            'language' => 'en',
            'generation_type' => 'article',
            'is_active' => true,
        ]);
        $this->pipelineB = ContentPipeline::create([
            'site_id' => $this->siteB->id,
            'topic_id' => $this->topicB->id,
            'prompt_id' => $this->promptB->id,
            'ai_provider_id' => $this->provider->id,
            'language' => 'en',
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        // Create Runs
        $this->runA = PipelineRun::create([
            'pipeline_id' => $this->pipelineA->id,
            'status' => 'failed',
        ]);
        $this->runB = PipelineRun::create([
            'pipeline_id' => $this->pipelineB->id,
            'status' => 'failed',
        ]);

        // Create Generated Content
        $this->contentA = GeneratedContent::create([
            'site_id' => $this->siteA->id,
            'topic_id' => $this->topicA->id,
            'pipeline_id' => $this->pipelineA->id,
            'title' => 'Content A',
            'content' => 'Body A',
            'status' => 'draft',
        ]);
        $this->contentB = GeneratedContent::create([
            'site_id' => $this->siteB->id,
            'topic_id' => $this->topicB->id,
            'pipeline_id' => $this->pipelineB->id,
            'title' => 'Content B',
            'content' => 'Body B',
            'status' => 'draft',
        ]);

        // Create Publishing Log
        $this->publishingLogA = PublishingLog::create([
            'generated_content_id' => $this->contentA->id,
            'site_id' => $this->siteA->id,
            'user_id' => $this->tenantAdminA->id,
            'status' => 'pending',
        ]);
        $this->publishingLogB = PublishingLog::create([
            'generated_content_id' => $this->contentB->id,
            'site_id' => $this->siteB->id,
            'user_id' => $this->tenantAdminB->id,
            'status' => 'pending',
        ]);
    }

    public function test_tenant_a_cannot_access_tenant_b_pipeline(): void
    {
        // List (index)
        $response = $this->actingAs($this->tenantAdminA)->getJson('/api/v1/pipelines');
        $response->assertStatus(200);
        $pipelineIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($pipelineIds->contains($this->pipelineA->id));
        $this->assertFalse($pipelineIds->contains($this->pipelineB->id));

        // Show B
        $response = $this->actingAs($this->tenantAdminA)->getJson("/api/v1/pipelines/{$this->pipelineB->id}");
        $response->assertStatus(404);

        // Update B
        $response = $this->actingAs($this->tenantAdminA)->putJson("/api/v1/pipelines/{$this->pipelineB->id}", [
            'language' => 'fr',
        ]);
        $response->assertStatus(404);

        // Delete B
        $response = $this->actingAs($this->tenantAdminA)->deleteJson("/api/v1/pipelines/{$this->pipelineB->id}");
        $response->assertStatus(404);

        // Execute B
        $response = $this->actingAs($this->tenantAdminA)->postJson("/api/v1/pipelines/{$this->pipelineB->id}/execute");
        $response->assertStatus(404);

        // History B
        $response = $this->actingAs($this->tenantAdminA)->getJson("/api/v1/pipelines/{$this->pipelineB->id}/history");
        $response->assertStatus(404);

        // Retry B run
        $response = $this->actingAs($this->tenantAdminA)->postJson("/api/v1/pipelines/runs/{$this->runB->id}/retry");
        $response->assertStatus(404);

        // Cancel B run
        $response = $this->actingAs($this->tenantAdminA)->postJson("/api/v1/pipelines/runs/{$this->runB->id}/cancel");
        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_access_tenant_b_topic(): void
    {
        // List
        $response = $this->actingAs($this->tenantAdminA)->getJson('/api/v1/topics');
        $response->assertStatus(200);
        $topicIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($topicIds->contains($this->topicA->id));
        $this->assertFalse($topicIds->contains($this->topicB->id));

        // Show B
        $response = $this->actingAs($this->tenantAdminA)->getJson("/api/v1/topics/{$this->topicB->id}");
        $response->assertStatus(404);

        // Update B
        $response = $this->actingAs($this->tenantAdminA)->putJson("/api/v1/topics/{$this->topicB->id}", [
            'name' => 'Modified B',
        ]);
        $response->assertStatus(404);

        // Delete B
        $response = $this->actingAs($this->tenantAdminA)->deleteJson("/api/v1/topics/{$this->topicB->id}");
        $response->assertStatus(404);

        // Restore B
        $this->topicB->delete();
        $response = $this->actingAs($this->tenantAdminA)->postJson("/api/v1/topics/{$this->topicB->id}/restore");
        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_access_tenant_b_generated_content(): void
    {
        // List
        $response = $this->actingAs($this->tenantAdminA)->getJson('/api/v1/articles');
        $response->assertStatus(200);
        $contentIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($contentIds->contains($this->contentA->id));
        $this->assertFalse($contentIds->contains($this->contentB->id));

        // Show B
        $response = $this->actingAs($this->tenantAdminA)->getJson("/api/v1/articles/{$this->contentB->id}");
        $response->assertStatus(404);

        // Update B
        $response = $this->actingAs($this->tenantAdminA)->putJson("/api/v1/articles/{$this->contentB->id}", [
            'title' => 'Modified B',
        ]);
        $response->assertStatus(404);

        // Update Status B
        $response = $this->actingAs($this->tenantAdminA)->putJson("/api/v1/articles/{$this->contentB->id}/status", [
            'status' => 'approved',
        ]);
        $response->assertStatus(404);

        // Revisions B
        $response = $this->actingAs($this->tenantAdminA)->getJson("/api/v1/articles/{$this->contentB->id}/revisions");
        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_access_tenant_b_publishing_log(): void
    {
        // List
        $response = $this->actingAs($this->tenantAdminA)->getJson('/api/v1/publishing/logs');
        $response->assertStatus(200);
        $logIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($logIds->contains($this->publishingLogA->id));
        $this->assertFalse($logIds->contains($this->publishingLogB->id));

        // Show B
        $response = $this->actingAs($this->tenantAdminA)->getJson("/api/v1/publishing/logs/{$this->publishingLogB->id}");
        $response->assertStatus(404);

        // Publish to other site B
        $response = $this->actingAs($this->tenantAdminA)->postJson("/api/v1/articles/{$this->contentA->id}/publish", [
            'site_id' => $this->siteB->id,
            'wp_status' => 'publish',
        ]);
        $response->assertStatus(403);

        // Retry B
        $response = $this->actingAs($this->tenantAdminA)->postJson("/api/v1/publishing/logs/{$this->publishingLogB->id}/retry");
        $response->assertStatus(404);

        // Cancel B
        $response = $this->actingAs($this->tenantAdminA)->postJson("/api/v1/publishing/logs/{$this->publishingLogB->id}/cancel");
        $response->assertStatus(404);

        // Sync B
        $response = $this->actingAs($this->tenantAdminA)->postJson("/api/v1/publishing/logs/{$this->publishingLogB->id}/sync");
        $response->assertStatus(404);
    }

    public function test_superadmin_can_access_all(): void
    {
        // List pipelines
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/pipelines');
        $response->assertStatus(200);
        $pipelineIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($pipelineIds->contains($this->pipelineA->id));
        $this->assertTrue($pipelineIds->contains($this->pipelineB->id));

        // List topics
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/topics');
        $response->assertStatus(200);
        $topicIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($topicIds->contains($this->topicA->id));
        $this->assertTrue($topicIds->contains($this->topicB->id));
    }
}
