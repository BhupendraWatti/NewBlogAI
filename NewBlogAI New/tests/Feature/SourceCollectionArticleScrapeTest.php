<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\ContentPipeline\Services\SourceCollectionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SourceCollectionArticleScrapeTest extends TestCase
{
    public function test_scraper_fetches_vertex_grounding_redirect_urls(): void
    {
        $url = 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/test-token';

        Http::fake([
            $url => Http::response(
                '<html><body><p>This verified publisher report contains enough factual article text to anchor content generation safely.</p></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $body = app(SourceCollectionService::class)->scrapeArticleBody($url);

        $this->assertStringContainsString('verified publisher report', $body);
        Http::assertSent(fn ($request) => $request->url() === $url);
    }

    public function test_scraper_falls_back_to_json_ld_article_body(): void
    {
        Http::fake([
            'https://example.com/report' => Http::response(<<<'HTML'
<html><head><script type="application/ld+json">
{"@type":"NewsArticle","articleBody":"Police said the collision occurred near Highway 44 at 8:30 PM. Six injured passengers remain in hospital while the investigation continues."}
</script></head><body><div>No paragraph tags here.</div></body></html>
HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $body = app(SourceCollectionService::class)->scrapeArticleBody('https://example.com/report');

        $this->assertStringContainsString('Highway 44 at 8:30 PM', $body);
        $this->assertStringContainsString('investigation continues', $body);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/report'
            && $request->hasHeader('Referer', 'https://example.com/')
            && $request->hasHeader('Sec-Fetch-Mode', 'navigate'));
    }
}
