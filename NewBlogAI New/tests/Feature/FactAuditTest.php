<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\ContentPipeline\Contracts\FactAuditorInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\DTOs\SourceDTO;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\AIProviderManager\Models\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;
    protected Topic $topic;
    protected Prompt $prompt;
    protected AIProvider $provider;
    protected ContentPipeline $pipeline;
    protected PipelineRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'domain_url' => 'https://example-test.com',
            'api_key' => 'test-token',
            'is_active' => true,
        ]);

        $this->topic = Topic::create([
            'name' => 'Laravel 12 Features',
            'category' => 'Tech',
            'language' => 'en',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);

        $this->prompt = Prompt::create([
            'name' => 'Test Prompt',
            'prompt' => 'Write about {{topic}}',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        $this->provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Google Gemini',
            'api_key' => 'some-api-key',
            'default_model' => 'gemini-2.5-flash',
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

        $this->run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'processing',
        ]);
    }

    /**
     * Test that FactAuditService successfully extracts and audits claims dynamically.
     */
    public function test_fact_audit_service_verification_and_scoring(): void
    {
        $auditor = app(FactAuditorInterface::class);

        $context = new PipelineContext($this->run, $this->pipeline);
        
        // Inject generated content with various factual statements
        $context->generatedContent = "# Laravel 12 Updates\n\nTaylor Otwell released Laravel 12 in 2026. The new version achieves 90% better performance. However, PHP 9 is now deprecated according to some rumors.";

        // Inject research sources (one matching the first claim, one matching the second claim, none matching the third)
        $source1 = new SourceDTO(
            url: 'https://laravel.com/blog/laravel-12',
            title: 'Laravel 12 Release Details',
            snippet: 'Taylor Otwell released Laravel 12 in early 2026 with amazing updates.',
            relevanceScore: 0.9
        );

        $source2 = new SourceDTO(
            url: 'https://laravel-news.com/benchmarks',
            title: 'Performance Benchmarks of Laravel 12',
            snippet: 'Benchmarks show the new version achieves 90% better performance on HTTP requests.',
            relevanceScore: 0.8
        );

        $context->addSource($source1);
        $context->addSource($source2);

        // Run auditor
        $context = $auditor->handle($context);

        $this->assertFalse($context->hasErrors());
        $this->assertArrayHasKey('fact_audit', $context->metadata);

        $audit = $context->metadata['fact_audit'];

        // Assert structural output
        $this->assertArrayHasKey('fact_score', $audit);
        $this->assertArrayHasKey('confidence_score', $audit);
        $this->assertArrayHasKey('supported_claims', $audit);
        $this->assertArrayHasKey('unsupported_claims', $audit);
        $this->assertArrayHasKey('references', $audit);

        // Verify claims extraction and categorization
        $supportedClaims = $audit['supported_claims'];
        $unsupportedClaims = $audit['unsupported_claims'];

        $this->assertNotEmpty($supportedClaims);
        $this->assertNotEmpty($unsupportedClaims);

        // The first claim (Taylor Otwell released Laravel 12 in 2026) should be supported
        $foundFirstClaim = false;
        foreach ($supportedClaims as $item) {
            if (str_contains($item['claim'], 'Taylor Otwell released Laravel 12')) {
                $foundFirstClaim = true;
                // Verify references are correctly linked to the claim
                $this->assertNotEmpty($item['sources']);
                $this->assertEquals('https://laravel.com/blog/laravel-12', $item['sources'][0]['url']);
            }
        }
        $this->assertTrue($foundFirstClaim, 'First claim should be categorized as supported.');

        // The second claim (The new version achieves 90% better performance) should be supported
        $foundSecondClaim = false;
        foreach ($supportedClaims as $item) {
            if (str_contains($item['claim'], '90% better performance')) {
                $foundSecondClaim = true;
                $this->assertNotEmpty($item['sources']);
                $this->assertEquals('https://laravel-news.com/benchmarks', $item['sources'][0]['url']);
            }
        }
        $this->assertTrue($foundSecondClaim, 'Second claim should be categorized as supported.');

        // The third claim (PHP 9 is now deprecated) should be unsupported since there's no source for it
        $foundThirdClaim = false;
        foreach ($unsupportedClaims as $claimText) {
            if (str_contains($claimText, 'PHP 9 is now deprecated')) {
                $foundThirdClaim = true;
            }
        }
        $this->assertTrue($foundThirdClaim, 'Third claim should be categorized as unsupported.');

        // Assert dynamic score calculations
        // We have 3 claims: 2 supported, 1 unsupported -> fact score should be round(2/3 * 100) = 67
        $this->assertEquals(67, $audit['fact_score']);
        
        // Confidence score should be non-zero and less than 1.0 (since there are unsupported claims)
        $this->assertGreaterThan(0.0, $audit['confidence_score']);
        $this->assertLessThan(1.0, $audit['confidence_score']);
        
        // Assert references contains our two matching sources
        $this->assertCount(2, $audit['references']);
        $urls = array_column($audit['references'], 'url');
        $this->assertContains('https://laravel.com/blog/laravel-12', $urls);
        $this->assertContains('https://laravel-news.com/benchmarks', $urls);
    }
}
