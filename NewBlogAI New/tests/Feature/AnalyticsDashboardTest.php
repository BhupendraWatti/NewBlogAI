<?php

namespace Tests\Feature;

use App\Modules\Analytics\Services\AnalyticsService;
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
use Carbon\Carbon;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsService $analyticsService;
    protected Site $site1;
    protected Site $site2;
    protected AIProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyticsService = app(AnalyticsService::class);

        // 1. Create a customer & subscription
        $customer = Customer::create([
            'company_name' => 'Analytics Test Corp',
            'owner_name' => 'Alice Smith',
            'email' => 'alice@test.com',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'name' => 'Enterprise Plan',
            'monthly_price' => 199.00,
            'yearly_price' => 1990.00,
            'max_wordpress_sites' => 10,
            'max_topics' => 100,
            'publishing_schedule_limit' => 50,
            'max_articles_per_day' => 100,
            'monthly_generation_limit' => 1000,
            'prompt_templates_allowed' => 100,
            'ai_providers_available' => ['openai', 'gemini'],
            'api_keys_allowed' => 10,
            'storage_limit' => 50000,
            'status' => 'active',
        ]);

        Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->addDays(30),
            'limits' => $plan->toArray(),
        ]);

        // 2. Create two sites for isolation testing
        $this->site1 = Site::create([
            'customer_id' => $customer->id,
            'domain_url' => 'https://site1.com',
            'api_key' => 'token1',
            'is_active' => true,
        ]);

        $this->site2 = Site::create([
            'customer_id' => $customer->id,
            'domain_url' => 'https://site2.com',
            'api_key' => 'token2',
            'is_active' => true,
        ]);

        $this->provider = AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'test-key',
            'default_model' => 'gpt-4o',
            'is_enabled' => true,
        ]);
    }

    public function test_daily_generation_stats(): void
    {
        $topic = Topic::create([
            'name' => 'Daily Stats Topic',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        // Site 1 today (2 articles)
        $c1 = GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'title' => 'Article 1',
            'content' => 'Content 1',
            'status' => 'published',
        ]);
        $c2 = GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'title' => 'Article 2',
            'content' => 'Content 2',
            'status' => 'published',
        ]);

        // Site 1 yesterday (1 article)
        $c3 = GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'title' => 'Article 3',
            'content' => 'Content 3',
            'status' => 'published',
        ]);
        GeneratedContent::where('id', $c3->id)->update(['created_at' => Carbon::now()->subDay()]);

        // Site 1 5 days ago (3 articles)
        for ($i = 0; $i < 3; $i++) {
            $c = GeneratedContent::create([
                'site_id' => $this->site1->id,
                'topic_id' => $topic->id,
                'title' => "Article 5d - {$i}",
                'content' => 'Content',
                'status' => 'published',
            ]);
            GeneratedContent::where('id', $c->id)->update(['created_at' => Carbon::now()->subDays(5)]);
        }

        // Site 1 45 days ago (1 article - outside 30 days limit)
        $cOld = GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'title' => 'Old Article',
            'content' => 'Content',
            'status' => 'published',
        ]);
        GeneratedContent::where('id', $cOld->id)->update(['created_at' => Carbon::now()->subDays(45)]);

        // Site 2 today (5 articles - should be isolated)
        GeneratedContent::create([
            'site_id' => $this->site2->id,
            'topic_id' => $topic->id,
            'title' => 'Site 2 Article',
            'content' => 'Content',
            'status' => 'published',
        ]);

        $stats = $this->analyticsService->getDailyGenerationStats($this->site1->id, 30);

        $todayStr = Carbon::now()->format('Y-m-d');
        $yesterdayStr = Carbon::now()->subDay()->format('Y-m-d');
        $fiveDaysAgoStr = Carbon::now()->subDays(5)->format('Y-m-d');

        $this->assertCount(30, $stats);
        $this->assertEquals(2, $stats[$todayStr]);
        $this->assertEquals(1, $stats[$yesterdayStr]);
        $this->assertEquals(3, $stats[$fiveDaysAgoStr]);
        $this->assertEquals(0, $stats[Carbon::now()->subDays(10)->format('Y-m-d')]);
    }

    public function test_monthly_generation_stats(): void
    {
        $topic = Topic::create([
            'name' => 'Monthly Stats Topic',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        // Site 1 current month (2 articles)
        GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'title' => 'Article M1',
            'content' => 'Content',
        ]);
        GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'title' => 'Article M2',
            'content' => 'Content',
        ]);

        // Site 1 2 months ago (3 articles)
        for ($i = 0; $i < 3; $i++) {
            $c = GeneratedContent::create([
                'site_id' => $this->site1->id,
                'topic_id' => $topic->id,
                'title' => "Article M2 - {$i}",
                'content' => 'Content',
            ]);
            GeneratedContent::where('id', $c->id)->update(['created_at' => Carbon::now()->subMonths(2)]);
        }

        // Site 1 15 months ago (1 article - excluded)
        $cOld = GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'title' => 'Old Monthly Article',
            'content' => 'Content',
        ]);
        GeneratedContent::where('id', $cOld->id)->update(['created_at' => Carbon::now()->subMonths(15)]);

        // Site 2 current month (4 articles - isolated)
        GeneratedContent::create([
            'site_id' => $this->site2->id,
            'topic_id' => $topic->id,
            'title' => 'Site 2 Article',
            'content' => 'Content',
        ]);

        $stats = $this->analyticsService->getMonthlyGenerationStats($this->site1->id, 12);

        $currentMonthStr = Carbon::now()->format('Y-m');
        $twoMonthsAgoStr = Carbon::now()->subMonths(2)->format('Y-m');

        $this->assertCount(12, $stats);
        $this->assertEquals(2, $stats[$currentMonthStr]);
        $this->assertEquals(3, $stats[$twoMonthsAgoStr]);
        $this->assertEquals(0, $stats[Carbon::now()->subMonths(5)->format('Y-m')]);
    }

    public function test_token_usage_stats(): void
    {
        // Site 1 today
        AIRequestLog::create([
            'site_id' => $this->site1->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 120,
            'prompt_tokens' => 100,
            'completion_tokens' => 200,
            'total_tokens' => 300,
            'estimated_cost' => 0.005,
            'status' => 'success',
        ]);

        // Site 1 yesterday
        $logYest = AIRequestLog::create([
            'site_id' => $this->site1->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 100,
            'prompt_tokens' => 50,
            'completion_tokens' => 100,
            'total_tokens' => 150,
            'estimated_cost' => 0.002,
            'status' => 'success',
        ]);
        AIRequestLog::where('id', $logYest->id)->update(['created_at' => Carbon::now()->subDay()]);

        // Site 1 45 days ago (outside 30 days)
        $logOld = AIRequestLog::create([
            'site_id' => $this->site1->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 100,
            'prompt_tokens' => 1000,
            'completion_tokens' => 2000,
            'total_tokens' => 3000,
            'estimated_cost' => 0.05,
            'status' => 'success',
        ]);
        AIRequestLog::where('id', $logOld->id)->update(['created_at' => Carbon::now()->subDays(45)]);

        // Site 2 today (should be isolated)
        AIRequestLog::create([
            'site_id' => $this->site2->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 100,
            'prompt_tokens' => 500,
            'completion_tokens' => 500,
            'total_tokens' => 1000,
            'estimated_cost' => 0.01,
            'status' => 'success',
        ]);

        $stats = $this->analyticsService->getTokenUsageStats($this->site1->id, 30);

        $this->assertEquals(150, $stats['prompt_tokens']);
        $this->assertEquals(300, $stats['completion_tokens']);
        $this->assertEquals(450, $stats['total_tokens']);
    }

    public function test_cost_estimation_stats(): void
    {
        // Site 1 today
        AIRequestLog::create([
            'site_id' => $this->site1->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 120,
            'estimated_cost' => 0.005,
            'status' => 'success',
        ]);

        // Site 1 yesterday
        $logYest = AIRequestLog::create([
            'site_id' => $this->site1->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 100,
            'estimated_cost' => 0.002,
            'status' => 'success',
        ]);
        AIRequestLog::where('id', $logYest->id)->update(['created_at' => Carbon::now()->subDay()]);

        // Site 1 45 days ago
        $logOld = AIRequestLog::create([
            'site_id' => $this->site1->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 100,
            'estimated_cost' => 0.05,
            'status' => 'success',
        ]);
        AIRequestLog::where('id', $logOld->id)->update(['created_at' => Carbon::now()->subDays(45)]);

        // Site 2 today (should be isolated)
        AIRequestLog::create([
            'site_id' => $this->site2->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 100,
            'estimated_cost' => 0.01,
            'status' => 'success',
        ]);

        $stats = $this->analyticsService->getCostEstimationStats($this->site1->id, 30);

        $todayStr = Carbon::now()->format('Y-m-d');
        $yesterdayStr = Carbon::now()->subDay()->format('Y-m-d');
        $twoDaysAgoStr = Carbon::now()->subDays(2)->format('Y-m-d');

        $this->assertCount(30, $stats);
        $this->assertEquals(0.002, $stats[$yesterdayStr]);
        $this->assertEquals(0.007, $stats[$todayStr]);
        $this->assertEquals(0.0, $stats[$twoDaysAgoStr]);
    }

    public function test_success_rate_stats(): void
    {
        $topic = Topic::create([
            'name' => 'Success Rate Topic',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        $prompt = Prompt::create([
            'name' => 'Prompt',
            'prompt' => 'Text',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        // Pipeline 1 on Site 1
        $pipeline1 = ContentPipeline::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $this->provider->id,
        ]);

        // Pipeline 2 on Site 2
        $pipeline2 = ContentPipeline::create([
            'site_id' => $this->site2->id,
            'topic_id' => $topic->id,
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $this->provider->id,
        ]);

        // Site 1: 1 completed pipeline run, 2 failed
        PipelineRun::create(['pipeline_id' => $pipeline1->id, 'status' => 'completed']);
        PipelineRun::create(['pipeline_id' => $pipeline1->id, 'status' => 'failed', 'error_message' => 'API Timeout']);
        PipelineRun::create(['pipeline_id' => $pipeline1->id, 'status' => 'failed', 'error_message' => 'Rate Limit']);

        // Site 2: 5 completed pipeline runs (isolated)
        for ($i = 0; $i < 5; $i++) {
            PipelineRun::create(['pipeline_id' => $pipeline2->id, 'status' => 'completed']);
        }

        // Site 1: 3 completed publishing logs, 1 failed
        $c = GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'title' => 'Article',
            'content' => 'Content',
        ]);

        PublishingLog::create(['generated_content_id' => $c->id, 'site_id' => $this->site1->id, 'status' => 'completed']);
        PublishingLog::create(['generated_content_id' => $c->id, 'site_id' => $this->site1->id, 'status' => 'completed']);
        PublishingLog::create(['generated_content_id' => $c->id, 'site_id' => $this->site1->id, 'status' => 'completed']);
        PublishingLog::create(['generated_content_id' => $c->id, 'site_id' => $this->site1->id, 'status' => 'failed', 'error_message' => 'Publishing Error']);

        // Site 2: 4 failed publishing logs (isolated)
        $c2 = GeneratedContent::create([
            'site_id' => $this->site2->id,
            'topic_id' => $topic->id,
            'title' => 'Article 2',
            'content' => 'Content',
        ]);
        for ($i = 0; $i < 4; $i++) {
            PublishingLog::create(['generated_content_id' => $c2->id, 'site_id' => $this->site2->id, 'status' => 'failed', 'error_message' => 'Site 2 Error']);
        }

        $stats = $this->analyticsService->getSuccessRateStats($this->site1->id);

        $this->assertEquals(4, $stats['success']); // 1 pipeline run + 3 publishing
        $this->assertEquals(3, $stats['failed']);  // 2 pipeline run + 1 publishing
        $this->assertEquals(1, $stats['pipeline']['success']);
        $this->assertEquals(2, $stats['pipeline']['failed']);
        $this->assertEquals(3, $stats['publishing']['success']);
        $this->assertEquals(1, $stats['publishing']['failed']);
    }

    public function test_publish_failures(): void
    {
        $topic = Topic::create([
            'name' => 'Failure Topic',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        $prompt = Prompt::create([
            'name' => 'Prompt',
            'prompt' => 'Text',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        $pipeline = ContentPipeline::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $this->provider->id,
        ]);

        // Site 1 failures
        PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'failed', 'error_message' => 'API Timeout']);
        PipelineRun::create(['pipeline_id' => $pipeline->id, 'status' => 'failed', 'error_message' => 'Rate Limit']);

        $c = GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topic->id,
            'title' => 'Article',
            'content' => 'Content',
        ]);

        PublishingLog::create(['generated_content_id' => $c->id, 'site_id' => $this->site1->id, 'status' => 'failed', 'error_message' => 'Publishing Error']);
        PublishingLog::create(['generated_content_id' => $c->id, 'site_id' => $this->site1->id, 'status' => 'failed', 'error_message' => 'API Timeout']);

        // Site 2 failures (isolated)
        $pipeline2 = ContentPipeline::create([
            'site_id' => $this->site2->id,
            'topic_id' => $topic->id,
            'prompt_id' => $prompt->id,
            'ai_provider_id' => $this->provider->id,
        ]);
        PipelineRun::create(['pipeline_id' => $pipeline2->id, 'status' => 'failed', 'error_message' => 'Site 2 API Timeout']);

        $stats = $this->analyticsService->getPublishFailures($this->site1->id);

        $this->assertCount(3, $stats);
        $this->assertEquals('API Timeout', $stats[0]['error']);
        $this->assertEquals(2, $stats[0]['count']);

        $failuresCollect = collect($stats)->pluck('count', 'error');
        $this->assertEquals(1, $failuresCollect['Rate Limit']);
        $this->assertEquals(1, $failuresCollect['Publishing Error']);
        $this->assertArrayNotHasKey('Site 2 API Timeout', $failuresCollect);
    }

    public function test_category_coverage_stats(): void
    {
        // 1. CategoryEmpty
        $topicEmpty = Topic::create([
            'name' => 'Topic Empty',
            'category' => 'CategoryEmpty',
            'status' => 'active',
        ]);
        ContentPipeline::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topicEmpty->id,
            'prompt_id' => Prompt::create(['name' => 'P1', 'prompt' => 'Text', 'category' => 'CategoryEmpty'])->id,
            'ai_provider_id' => $this->provider->id,
        ]);

        // 2. CategoryFresh
        $topicFresh = Topic::create([
            'name' => 'Topic Fresh',
            'category' => 'CategoryFresh',
            'status' => 'active',
        ]);
        ContentPipeline::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topicFresh->id,
            'prompt_id' => Prompt::create(['name' => 'P2', 'prompt' => 'Text', 'category' => 'CategoryFresh'])->id,
            'ai_provider_id' => $this->provider->id,
        ]);
        GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topicFresh->id,
            'title' => 'Fresh Article',
            'content' => 'Content',
        ]);

        // 3. CategoryTrending (3 articles today)
        $topicTrending = Topic::create([
            'name' => 'Topic Trending',
            'category' => 'CategoryTrending',
            'status' => 'active',
        ]);
        ContentPipeline::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topicTrending->id,
            'prompt_id' => Prompt::create(['name' => 'P3', 'prompt' => 'Text', 'category' => 'CategoryTrending'])->id,
            'ai_provider_id' => $this->provider->id,
        ]);
        for ($i = 0; $i < 3; $i++) {
            GeneratedContent::create([
                'site_id' => $this->site1->id,
                'topic_id' => $topicTrending->id,
                'title' => "Trending Article {$i}",
                'content' => 'Content',
            ]);
        }

        // 4. CategoryStale (1 article 10 days ago)
        $topicStale = Topic::create([
            'name' => 'Topic Stale',
            'category' => 'CategoryStale',
            'status' => 'active',
        ]);
        ContentPipeline::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topicStale->id,
            'prompt_id' => Prompt::create(['name' => 'P4', 'prompt' => 'Text', 'category' => 'CategoryStale'])->id,
            'ai_provider_id' => $this->provider->id,
        ]);
        $cStale = GeneratedContent::create([
            'site_id' => $this->site1->id,
            'topic_id' => $topicStale->id,
            'title' => 'Stale Article',
            'content' => 'Content',
        ]);
        GeneratedContent::where('id', $cStale->id)->update(['created_at' => Carbon::now()->subDays(10)]);

        // Site 2 category (should be isolated)
        $topicSite2 = Topic::create([
            'name' => 'Topic Site 2',
            'category' => 'CategorySite2Only',
            'status' => 'active',
        ]);
        ContentPipeline::create([
            'site_id' => $this->site2->id,
            'topic_id' => $topicSite2->id,
            'prompt_id' => Prompt::create(['name' => 'P5', 'prompt' => 'Text', 'category' => 'CategorySite2Only'])->id,
            'ai_provider_id' => $this->provider->id,
        ]);

        $stats = $this->analyticsService->getCategoryCoverageStats($this->site1->id);

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(1, $stats['counts']['empty']);
        $this->assertEquals(1, $stats['counts']['fresh']);
        $this->assertEquals(1, $stats['counts']['trending']);
        $this->assertEquals(1, $stats['counts']['stale']);

        $this->assertEquals(25.0, $stats['percentages']['empty']);
        $this->assertEquals(25.0, $stats['percentages']['fresh']);
        $this->assertEquals(25.0, $stats['percentages']['trending']);
        $this->assertEquals(25.0, $stats['percentages']['stale']);
    }

    public function test_provider_usage_stats(): void
    {
        // Site 1: OpenAI request 1
        AIRequestLog::create([
            'site_id' => $this->site1->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 100,
            'prompt_tokens' => 100,
            'completion_tokens' => 200,
            'total_tokens' => 300,
            'estimated_cost' => 0.005,
            'status' => 'success',
        ]);

        // Site 1: OpenAI request 2
        AIRequestLog::create([
            'site_id' => $this->site1->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 110,
            'prompt_tokens' => 50,
            'completion_tokens' => 100,
            'total_tokens' => 150,
            'estimated_cost' => 0.002,
            'status' => 'success',
        ]);

        // Site 1: Gemini request 1
        AIRequestLog::create([
            'site_id' => $this->site1->id,
            'provider' => 'gemini',
            'model' => 'gemini-1.5-pro',
            'execution_time_ms' => 150,
            'prompt_tokens' => 100,
            'completion_tokens' => 100,
            'total_tokens' => 200,
            'estimated_cost' => 0.001,
            'status' => 'success',
        ]);

        // Site 2 request (isolated)
        AIRequestLog::create([
            'site_id' => $this->site2->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'execution_time_ms' => 120,
            'prompt_tokens' => 500,
            'completion_tokens' => 500,
            'total_tokens' => 1000,
            'estimated_cost' => 0.010,
            'status' => 'success',
        ]);

        $stats = $this->analyticsService->getProviderUsageStats($this->site1->id);

        $this->assertCount(2, $stats);

        $statsCollect = collect($stats)->keyBy(function ($item) {
            return $item['provider'] . '-' . $item['model'];
        });

        $this->assertArrayHasKey('openai-gpt-4o', $statsCollect);
        $this->assertEquals(2, $statsCollect['openai-gpt-4o']['request_count']);
        $this->assertEquals(150, $statsCollect['openai-gpt-4o']['prompt_tokens']);
        $this->assertEquals(300, $statsCollect['openai-gpt-4o']['completion_tokens']);
        $this->assertEquals(450, $statsCollect['openai-gpt-4o']['total_tokens']);
        $this->assertEquals(0.007, $statsCollect['openai-gpt-4o']['estimated_cost']);

        $this->assertArrayHasKey('gemini-gemini-1.5-pro', $statsCollect);
        $this->assertEquals(1, $statsCollect['gemini-gemini-1.5-pro']['request_count']);
        $this->assertEquals(100, $statsCollect['gemini-gemini-1.5-pro']['prompt_tokens']);
        $this->assertEquals(100, $statsCollect['gemini-gemini-1.5-pro']['completion_tokens']);
        $this->assertEquals(200, $statsCollect['gemini-gemini-1.5-pro']['total_tokens']);
        $this->assertEquals(0.001, $statsCollect['gemini-gemini-1.5-pro']['estimated_cost']);
    }

    public function test_controller_analytics_endpoints_and_isolation(): void
    {
        $user = \App\Models\User::create([
            'name' => 'Tenant Admin',
            'email' => 'tenant@company.com',
            'password' => bcrypt('password'),
            'customer_id' => $this->site1->customer_id,
        ]);
        $user->role = 2; // editor/admin
        $user->save();

        $strangerCustomer = Customer::create([
            'company_name' => 'Stranger Corp',
            'owner_name' => 'John Doe',
            'email' => 'john@stranger.com',
            'status' => 'active',
        ]);

        $strangerUser = \App\Models\User::create([
            'name' => 'Stranger User',
            'email' => 'stranger@company.com',
            'password' => bcrypt('password'),
            'customer_id' => $strangerCustomer->id,
        ]);
        $strangerUser->role = 2;
        $strangerUser->save();

        $superAdmin = \App\Models\User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@company.com',
            'password' => bcrypt('password'),
        ]);
        $superAdmin->role = 1;
        $superAdmin->save();

        $endpoints = [
            'coverage',
            'daily',
            'monthly',
            'tokens',
            'costs',
            'success-rate',
            'failures',
            'providers'
        ];

        foreach ($endpoints as $endpoint) {
            \Illuminate\Support\Facades\Auth::logout();
            $this->flushSession();
            $url = "/api/v1/sites/{$this->site1->id}/analytics/{$endpoint}";

            // 1. Unauthenticated gets redirected / forbidden
            $this->getJson($url)->assertStatus(401);

            // 2. Authenticated stranger gets 403
            $this->actingAs($strangerUser)
                ->getJson($url)
                ->assertStatus(403);

            // 3. Authenticated tenant owner gets 200
            $this->actingAs($user)
                ->getJson($url)
                ->assertStatus(200);

            // 4. Super admin gets 200
            $this->actingAs($superAdmin)
                ->getJson($url)
                ->assertStatus(200);
        }
    }
}
