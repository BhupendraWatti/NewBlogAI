<?php

namespace Tests\Feature;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Services\CoverageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageFreshnessTest extends TestCase
{
    use RefreshDatabase;

    protected CoverageService $coverageService;
    protected Site $site;

    /** @var array<string, ContentPipeline> Pipelines keyed by news_category */
    protected array $pipelines = [];

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
            'api_key' => 'test-key',
            'is_active' => true,
            'status' => 'connected',
            'timezone' => 'UTC',
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
            'api_key' => 'test-key',
            'default_model' => 'gpt-4o',
            'is_enabled' => true,
        ]);

        // Category-driven pipelines (ADR-003) — no topic FK
        foreach (['technology', 'science', 'sports', 'entertainment'] as $category) {
            $this->pipelines[$category] = ContentPipeline::create([
                'site_id' => $this->site->id,
                'news_category' => $category,
                'prompt_id' => $prompt->id,
                'ai_provider_id' => $provider->id,
                'language' => 'en',
                'generation_type' => 'article',
                'is_active' => true,
            ]);
        }
    }

    /**
     * Create generated content linked to a category pipeline at a point in time.
     */
    protected function createContentForCategory(string $category, string $title, Carbon $createdAt, array $metadata = []): GeneratedContent
    {
        $content = new GeneratedContent([
            'site_id' => $this->site->id,
            'pipeline_id' => $this->pipelines[$category]->id,
            'title' => $title,
            'content' => "Body for {$title}.",
            'status' => 'published',
            'metadata' => $metadata ?: null,
        ]);
        $content->timestamps = false;
        $content->created_at = $createdAt;
        $content->updated_at = $createdAt;
        $content->save();

        return $content;
    }

    public function test_category_freshness_status_calculations()
    {
        // 1. "empty" status: no generated content in the category
        $this->assertEquals('empty', $this->coverageService->getCategoryStatus($this->site->id, 'entertainment'));

        // 2. "stale" status: has content, but none in the last 7 days
        $this->createContentForCategory('science', 'Old Science Article', Carbon::now()->subDays(10));
        $this->assertEquals('stale', $this->coverageService->getCategoryStatus($this->site->id, 'science'));

        // 3. "fresh" status: has content in the last 7 days
        $this->createContentForCategory('sports', 'Fresh Match Report', Carbon::now()->subDays(5));
        $this->assertEquals('fresh', $this->coverageService->getCategoryStatus($this->site->id, 'sports'));

        // 4. "trending" status by volume: 3+ articles in the last 2 days
        for ($i = 0; $i < 3; $i++) {
            $this->createContentForCategory('technology', "Tech Article {$i}", Carbon::now()->subHours(12));
        }
        $this->assertEquals('trending', $this->coverageService->getCategoryStatus($this->site->id, 'technology'));

        // 5. "trending" status by metadata flag
        $this->createContentForCategory('sports', 'Trending Meta Sports Article', Carbon::now(), ['trending' => true]);
        $this->assertEquals('trending', $this->coverageService->getCategoryStatus($this->site->id, 'sports'));

        // 6. Category matching is case-insensitive
        $this->assertEquals('stale', $this->coverageService->getCategoryStatus($this->site->id, 'Science'));
    }

    public function test_recommendation_prioritization_order()
    {
        // Setup:
        // technology: 3+ articles in 2 days -> trending (not recommended)
        // science: 1 article 10 days ago -> stale (recommended second)
        // sports: 1 article 5 days ago -> fresh (not recommended)
        // entertainment: 0 articles -> empty (recommended first)

        $this->createContentForCategory('science', 'Old Science Article', Carbon::now()->subDays(10));
        $this->createContentForCategory('sports', 'Recent Sports Article', Carbon::now()->subDays(5));

        for ($i = 0; $i < 3; $i++) {
            $this->createContentForCategory('technology', "Tech Article {$i}", Carbon::now()->subHours(6));
        }

        $recommendations = $this->coverageService->getRecommendations($this->site->id);

        $this->assertCount(2, $recommendations);
        $this->assertEquals('entertainment', $recommendations[0]['category']);
        $this->assertEquals('empty', $recommendations[0]['status']);
        $this->assertEquals('science', $recommendations[1]['category']);
        $this->assertEquals('stale', $recommendations[1]['status']);
    }
}
