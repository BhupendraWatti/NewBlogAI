<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Key;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use App\Modules\SubscriptionManager\Services\UsageTrackingService;
use App\Modules\SubscriptionManager\Exceptions\EntitlementDeniedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected Plan $plan;
    protected Subscription $subscription;
    protected Site $site;
    protected Topic $topic;
    protected Prompt $prompt;
    protected AIProvider $provider;
    protected ContentPipeline $pipeline;
    protected EntitlementService $entitlementService;
    protected UsageTrackingService $usageTrackingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entitlementService = app(EntitlementService::class);
        $this->usageTrackingService = app(UsageTrackingService::class);

        $this->customer = Customer::create([
            'company_name' => 'Alpha Media Corp',
            'owner_name' => 'Bob Vance',
            'email' => 'bob@alpha.com',
            'status' => 'active',
        ]);

        $this->plan = Plan::create([
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

        $this->subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
            'limits' => array_merge($this->plan->toArray(), [
                'max_images_per_article' => 3,
            ]),
        ]);

        $this->site = Site::create([
            'customer_id' => $this->customer->id,
            'domain_url' => 'https://alpha.com',
            'api_key' => 'token',
            'is_active' => true,
        ]);

        $this->topic = Topic::create([
            'name' => 'Laravel Quotas',
            'category' => 'Tech',
            'status' => 'active',
            'generation_frequency' => 'daily',
            'subscription_id' => $this->subscription->id,
        ]);

        $this->prompt = Prompt::create([
            'name' => 'Quotas Prompt',
            'prompt' => 'Write about Larval Quotas.',
            'category' => 'Tech',
            'status' => 'active',
            'topic_id' => $this->topic->id,
        ]);

        $this->provider = AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'test-key',
            'default_model' => 'gpt-4o',
            'is_enabled' => true,
        ]);

        $this->pipeline = ContentPipeline::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'prompt_id' => $this->prompt->id,
            'ai_provider_id' => $this->provider->id,
            'language' => 'en',
            'generation_type' => 'article',
            'is_active' => true,
        ]);
    }

    public function test_can_run_pipeline_when_within_limits(): void
    {
        // Should not throw any exception
        $this->entitlementService->assertCanRunPipeline($this->site, $this->pipeline, [
            'featured_image' => 1,
            'inline_placeholders' => 1, // Total 2 images, limit is 3
            'localization' => true,      // Enabled in feature_flags
        ]);
        
        $this->assertTrue(true);
    }

    public function test_cannot_run_pipeline_when_site_inactive(): void
    {
        $this->site->update(['is_active' => false]);

        $this->expectException(EntitlementDeniedException::class);
        $this->expectExceptionMessage('The site must be active to run the pipeline.');

        $this->entitlementService->assertCanRunPipeline($this->site, $this->pipeline);
    }

    public function test_cannot_run_pipeline_when_monthly_quota_exceeded(): void
    {
        // Simulate 5 generations (limit is 5)
        for ($i = 0; $i < 5; $i++) {
            AIRequestLog::create([
                'customer_id' => $this->customer->id,
                'subscription_id' => $this->subscription->id,
                'site_id' => $this->site->id,
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'execution_time_ms' => 100,
                'status' => 'success',
            ]);
        }

        $this->expectException(EntitlementDeniedException::class);
        $this->expectExceptionMessage('monthly_generation_limit');

        $this->entitlementService->assertCanRunPipeline($this->site, $this->pipeline);
    }

    public function test_cannot_run_pipeline_when_daily_publishing_quota_exceeded(): void
    {
        // Daily limit is 2. Create 2 published logs for today.
        for ($i = 0; $i < 2; $i++) {
            $content = GeneratedContent::create([
                'site_id' => $this->site->id,
                'topic_id' => $this->topic->id,
                'pipeline_id' => $this->pipeline->id,
                'title' => 'Article ' . $i,
                'content' => 'Content of the article',
                'status' => 'approved',
            ]);

            PublishingLog::create([
                'generated_content_id' => $content->id,
                'site_id' => $this->site->id,
                'status' => 'completed',
            ]);
        }

        $this->expectException(EntitlementDeniedException::class);
        $this->expectExceptionMessage('max_articles_per_day');

        $this->entitlementService->assertCanRunPipeline($this->site, $this->pipeline);
    }

    public function test_cannot_run_pipeline_when_planned_images_exceed_limit(): void
    {
        $this->expectException(EntitlementDeniedException::class);
        $this->expectExceptionMessage('exceeds the allowed limit per article');

        $this->entitlementService->assertCanRunPipeline($this->site, $this->pipeline, [
            'featured_image' => 1,
            'inline_placeholders' => 3, // Total 4, limit is 3
        ]);
    }

    public function test_cannot_run_pipeline_when_api_keys_exceed_limit(): void
    {
        // Limit is 2. Create 3 active keys.
        $user = User::create([
            'name' => 'Dev User',
            'email' => 'dev@alpha.com',
            'password' => bcrypt('password'),
            'customer_id' => $this->customer->id,
        ]);

        Key::create(['name' => 'key1', 'key' => 'abc', 'user_id' => $user->id]);
        Key::create(['name' => 'key2', 'key' => 'def', 'user_id' => $user->id]);
        Key::create(['name' => 'key3', 'key' => 'ghi', 'user_id' => $user->id]);

        $this->expectException(EntitlementDeniedException::class);
        $this->expectExceptionMessage('api_keys_allowed');

        $this->entitlementService->assertCanRunPipeline($this->site, $this->pipeline);
    }

    public function test_cannot_run_pipeline_when_requested_feature_is_disabled(): void
    {
        $this->expectException(EntitlementDeniedException::class);
        $this->expectExceptionMessage('media_preparation'); // Not in feature_flags

        $this->entitlementService->assertCanRunPipeline($this->site, $this->pipeline, [
            'media_preparation' => true,
        ]);
    }

    public function test_usage_tracking_records_and_aggregates_metrics(): void
    {
        // Verify initial state
        $stats = $this->usageTrackingService->getUsageStats($this->site);
        $this->assertEquals(0, $stats['generated_articles']);
        $this->assertEquals(0, $stats['published_articles']);
        $this->assertEquals(0, $stats['prompt_tokens']);
        $this->assertEquals(0, $stats['estimated_cost']);

        // 1. Record some usage
        $this->usageTrackingService->recordUsage($this->site, [
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'prompt_tokens' => 150,
            'completion_tokens' => 300,
            'total_tokens' => 450,
            'estimated_cost' => 0.009,
            'image_generation_count' => 2,
            'video_generation_count' => 1,
            'prompt_count' => 1,
            'status' => 'success',
        ]);

        // 2. Create actual generated/published records
        $content = GeneratedContent::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'pipeline_id' => $this->pipeline->id,
            'title' => 'Article Title',
            'content' => 'Content of the article',
            'status' => 'draft',
        ]);

        PublishingLog::create([
            'generated_content_id' => $content->id,
            'site_id' => $this->site->id,
            'status' => 'completed',
        ]);

        // Get updated stats
        $stats = $this->usageTrackingService->getUsageStats($this->site);

        $this->assertEquals(1, $stats['generated_articles']);
        $this->assertEquals(1, $stats['published_articles']);
        $this->assertEquals(450, $stats['total_tokens']);
        $this->assertEquals(150, $stats['prompt_tokens']);
        $this->assertEquals(300, $stats['completion_tokens']);
        $this->assertEquals(0.009, $stats['estimated_cost']);
        $this->assertEquals(2, $stats['image_generations']);
        $this->assertEquals(1, $stats['video_generations']);
        $this->assertEquals(1, $stats['prompt_count']);
        
        $this->assertArrayHasKey('openai', $stats['provider_metrics']);
        $this->assertEquals(1, $stats['provider_metrics']['openai']['count']);
        $this->assertEquals(450, $stats['provider_metrics']['openai']['total_tokens']);
        $this->assertEquals(0.009, $stats['provider_metrics']['openai']['estimated_cost']);
    }
}
