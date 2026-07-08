<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\TopicManager\Services\CoverageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageFreshnessTest extends TestCase
{
    use RefreshDatabase;

    protected CoverageService $coverageService;
    protected Site $site;
    protected Topic $topicTech;
    protected Topic $topicScience;
    protected Topic $topicGaming;
    protected Topic $topicMovies;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coverageService = resolve(CoverageService::class);

        $customer = Customer::create([
            'company_name' => 'Acme Corp',
            'owner_name' => 'Alice owner',
            'email' => 'alice@acme.com',
            'status' => 'active',
        ]);

        $this->site = Site::create([
            'customer_id' => $customer->id,
            'domain_url' => 'https://acmeblog.com',
            'name' => 'Acme Blog',
            'api_key' => 'wp_app_password_value',
            'is_active' => true,
            'status' => 'connected',
            'timezone' => 'UTC',
        ]);

        // Create Topics with different categories
        $this->topicTech = Topic::create([
            'name' => 'Technology General',
            'category' => 'Technology',
            'status' => 'active',
        ]);

        $this->topicScience = Topic::create([
            'name' => 'Science Daily',
            'category' => 'Science',
            'status' => 'active',
        ]);

        $this->topicGaming = Topic::create([
            'name' => 'Gaming News',
            'category' => 'Gaming',
            'status' => 'active',
        ]);

        $this->topicMovies = Topic::create([
            'name' => 'Movie Reviews',
            'category' => 'Movies',
            'status' => 'active',
        ]);

        $prompt = Prompt::create([
            'name' => 'Standard Prompt',
            'prompt' => 'Write content.',
            'category' => 'General',
            'status' => 'active',
        ]);

        $provider = AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'some-key',
            'default_model' => 'gpt-4o',
            'is_enabled' => true,
        ]);

        // Create pipelines
        foreach ([$this->topicTech, $this->topicScience, $this->topicGaming, $this->topicMovies] as $topic) {
            ContentPipeline::create([
                'site_id' => $this->site->id,
                'topic_id' => $topic->id,
                'prompt_id' => $prompt->id,
                'ai_provider_id' => $provider->id,
                'language' => 'en',
                'generation_type' => 'article',
                'is_active' => true,
            ]);
        }
    }

    public function test_category_freshness_status_calculations()
    {
        // 1. "empty" status: no generated content
        $status = $this->coverageService->getCategoryStatus($this->site->id, 'Movies');
        $this->assertEquals('empty', $status);

        // 2. "stale" status: has content, but none in the last 7 days
        $contentStale = new GeneratedContent([
            'site_id' => $this->site->id,
            'topic_id' => $this->topicScience->id,
            'title' => 'Old Science Article',
            'content' => 'Old science content.',
            'status' => 'published',
        ]);
        $contentStale->timestamps = false;
        $contentStale->created_at = Carbon::now()->subDays(10);
        $contentStale->updated_at = Carbon::now()->subDays(10);
        $contentStale->save();

        $status = $this->coverageService->getCategoryStatus($this->site->id, 'Science');
        $this->assertEquals('stale', $status);

        // 3. "fresh" status: has content in the last 7 days
        $contentFresh = new GeneratedContent([
            'site_id' => $this->site->id,
            'topic_id' => $this->topicGaming->id,
            'title' => 'Fresh Game Review',
            'content' => 'Fresh gaming content.',
            'status' => 'published',
        ]);
        $contentFresh->timestamps = false;
        $contentFresh->created_at = Carbon::now()->subDays(5);
        $contentFresh->updated_at = Carbon::now()->subDays(5);
        $contentFresh->save();

        $status = $this->coverageService->getCategoryStatus($this->site->id, 'Gaming');
        $this->assertEquals('fresh', $status);

        // 4. "trending" status by volume: 3+ articles in the last 2 days
        for ($i = 0; $i < 3; $i++) {
            $contentTrend = new GeneratedContent([
                'site_id' => $this->site->id,
                'topic_id' => $this->topicTech->id,
                'title' => "Tech Article {$i}",
                'content' => "Tech content {$i}.",
                'status' => 'published',
            ]);
            $contentTrend->timestamps = false;
            $contentTrend->created_at = Carbon::now()->subHours(12);
            $contentTrend->updated_at = Carbon::now()->subHours(12);
            $contentTrend->save();
        }

        $status = $this->coverageService->getCategoryStatus($this->site->id, 'Technology');
        $this->assertEquals('trending', $status);

        // 5. "trending" status by metadata
        $contentTrendMeta = new GeneratedContent([
            'site_id' => $this->site->id,
            'topic_id' => $this->topicGaming->id,
            'title' => "Trending Meta Gaming Article",
            'content' => "Gaming content",
            'status' => 'published',
            'metadata' => ['trending' => true],
        ]);
        $contentTrendMeta->save();

        $status = $this->coverageService->getCategoryStatus($this->site->id, 'Gaming');
        $this->assertEquals('trending', $status);
    }

    public function test_recommendation_prioritization_order()
    {
        // Setup:
        // Technology: 3+ articles in 2 days -> trending (not recommended)
        // Science: 1 article 10 days ago -> stale (recommended)
        // Gaming: 1 article 5 days ago -> fresh (not recommended)
        // Movies: 0 articles -> empty (recommended first)

        // Science (stale)
        $contentScience = new GeneratedContent([
            'site_id' => $this->site->id,
            'topic_id' => $this->topicScience->id,
            'title' => 'Old Science Article',
            'content' => 'Content',
            'status' => 'published',
        ]);
        $contentScience->timestamps = false;
        $contentScience->created_at = Carbon::now()->subDays(10);
        $contentScience->updated_at = Carbon::now()->subDays(10);
        $contentScience->save();

        // Gaming (fresh)
        $contentGaming = new GeneratedContent([
            'site_id' => $this->site->id,
            'topic_id' => $this->topicGaming->id,
            'title' => 'Recent Gaming Article',
            'content' => 'Content',
            'status' => 'published',
        ]);
        $contentGaming->timestamps = false;
        $contentGaming->created_at = Carbon::now()->subDays(5);
        $contentGaming->updated_at = Carbon::now()->subDays(5);
        $contentGaming->save();

        // Technology (trending)
        for ($i = 0; $i < 3; $i++) {
            $contentTech = new GeneratedContent([
                'site_id' => $this->site->id,
                'topic_id' => $this->topicTech->id,
                'title' => "Tech Article {$i}",
                'content' => 'Content',
                'status' => 'published',
            ]);
            $contentTech->timestamps = false;
            $contentTech->created_at = Carbon::now()->subHours(6);
            $contentTech->updated_at = Carbon::now()->subHours(6);
            $contentTech->save();
        }

        // Get Recommendations
        $recommendations = $this->coverageService->getRecommendations($this->site->id);

        // Recommendations should have 2 elements:
        // 1st: Movies (empty)
        // 2nd: Science (stale)
        $this->assertCount(2, $recommendations);
        $this->assertEquals('Movies', $recommendations[0]['category']);
        $this->assertEquals('empty', $recommendations[0]['status']);

        $this->assertEquals('Science', $recommendations[1]['category']);
        $this->assertEquals('stale', $recommendations[1]['status']);
    }
}
