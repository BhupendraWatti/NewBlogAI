<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\ContentPipeline\Contracts\SourceCollectorInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\DTOs\SourceDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SourceCollectionService implements SourceCollectorInterface
{
    public function __construct(
        protected AIProviderService $providerService
    ) {}
    /**
     * Process the current stage of the content pipeline.
     * Simulates source collection based on research queries, normalizing and deduplicating them.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        try {
            Log::info('SourceCollectionService: Starting source collection.');

            $queries = $context->researchData['queries'] ?? [];
            $topic = $context->resolvedTopic;

            if (empty($queries) && empty($context->sources)) {
                if (empty($topic)) {
                    throw new \RuntimeException('No search queries or topic available for source collection.');
                }
                Log::warning('SourceCollectionService: No queries found in context. Creating default query from topic.');
                $queries = ["{$topic} latest news and overview"];
            }

            // Gather all raw sources
            $rawSources = [];

            // 1. Gather existing sources from context
            foreach ($context->sources as $existingSource) {
                if ($existingSource instanceof SourceDTO) {
                    $rawSources[] = $existingSource->toArray();
                } elseif (is_array($existingSource)) {
                    $rawSources[] = $existingSource;
                }
            }

            // 2. Gather real sources using the pipeline's AI provider
            $pipeline    = $context->pipeline;
            $provider    = $context->overrideProvider ?? $pipeline?->provider;
            $providerKey = $provider?->provider_key ?? 'gemini';
            $apiKey      = $provider?->api_key ?? '';
            $model       = $provider?->default_model ?? null;

            // Skip additional searches if newsroom candidate is already selected to save tokens
            $hasSelectedNews = is_array($context->metadata['selected_news'] ?? null);

            if (! $hasSelectedNews && !empty($apiKey)) {
                // Real-time background news search is only supported via Gemini Google Search grounding.
                // Non-grounded models cannot search their static weights for news from the last 48 hours.
                if (strtolower($providerKey) !== 'gemini' && !app()->environment('testing')) {
                    throw new \RuntimeException("Real-time news search is only supported via Gemini Google Search grounding. Non-grounded provider '{$providerKey}' cannot perform background news research without a selected newsroom candidate.");
                }

                // Limit search to the top 1 query to prevent rate limits and token usage
                $limitedQueries = array_slice($queries, 0, 1);
                foreach ($limitedQueries as $query) {
                    $realSources = $this->searchWithProvider($query, $topic ?? '', $providerKey, $apiKey, $model);
                    foreach ($realSources as $source) {
                        $rawSources[] = $source;
                    }
                }
            }

            // 3. Process (normalize, dedup, region detect, keyword extract, calculate relevance score, sort)
            $processedSources = $this->processSources($rawSources, $queries, $topic ?? '');

            // 4. Update the context's sources array with normalized SourceDTOs
            $context->sources = [];
            foreach ($processedSources as $sourceDto) {
                $context->addSource($sourceDto);
            }

            // 5. Cluster topics and attach tags to context metadata
            $clusters = $this->clusterTopics($processedSources);
            $context->metadata['clustered_topics'] = array_keys($clusters);
            $context->metadata['topic_clusters'] = $clusters;

            Log::info('SourceCollectionService: Source collection completed.', [
                'total_collected' => count($rawSources),
                'total_unique' => count($processedSources),
                'clusters_found' => count($clusters)
            ]);
        } catch (\Exception $e) {
            // Log at critical level when the exception originates from the fabrication-prevention
            // gate (non-Gemini provider in production) so it is distinguishable from ordinary
            // pipeline failures in log dashboards and alerting rules.
            $isFabricationGate = str_contains($e->getMessage(), 'Real-time news search is only supported via Gemini');
            if ($isFabricationGate) {
                Log::critical('SourceCollectionService: Fabrication-prevention gate activated — non-grounded provider blocked.', [
                    'message' => $e->getMessage(),
                ]);
            } else {
                Log::error('SourceCollectionService failed: ' . $e->getMessage(), [
                    'exception' => $e
                ]);
            }
            $context->addError('source_collector', $e->getMessage());
        }

        return $context;
    }

    /**
     * Process, normalize, deduplicate, and rank raw source arrays.
     *
     * Enforces strict temporal filtering: sources with no parseable publish date
     * AND whose URL does not contain the current year are treated as potentially
     * stale and dropped to prevent legacy articles from being pulled in as live news.
     *
     * Returns an array of sorted SourceDTOs.
     */
    protected function processSources(array $rawSources, array $queries, string $topic): array
    {
        $uniqueSources = [];
        $currentYear   = (int) now()->format('Y');
        $cutoffDate    = now()->subDays(7)->startOfDay(); // accept up to 7 days old

        foreach ($rawSources as $raw) {
            $url = $raw['url'] ?? '';
            if (empty($url)) {
                continue;
            }

            // Normalize URL
            $normalizedUrl = $this->normalizeUrl($url);

            // Deduplicate strictly by normalized URL
            if (isset($uniqueSources[$normalizedUrl])) {
                continue;
            }

            // Lock origin_url at extraction time so publisher is never
            // detached from its source URL during concurrent processing.
            $originUrl = $normalizedUrl;

            // Extract fields
            $metadata = $raw['metadata'] ?? [];
            $title = $raw['title'] ?? null;
            $snippet = $raw['snippet'] ?? null;

            // Derive publisher strictly from the origin URL domain, not from metadata
            // that may have been assembled from a different source.
            // Only fall back to metadata publisher if the origin_url host matches
            // the declared publisher domain (loose heuristic).
            $declaredPublisher = $raw['publisher'] ?? $metadata['publisher'] ?? null;
            $originHost        = strtolower(parse_url($originUrl, PHP_URL_HOST) ?? '');
            if ($declaredPublisher && str_contains($originHost, strtolower(explode('.', (string) $declaredPublisher)[0] ?? ''))) {
                $publisher = trim(strip_tags((string) $declaredPublisher));
            } else {
                // Derive cleanly from host, stripping www. prefix
                $publisher = preg_replace('/^www\./', '', $originHost) ?: null;
            }

            $author = $raw['author'] ?? $metadata['author'] ?? null;
            $publishedDate = $raw['published_date'] ?? $metadata['published_date'] ?? $raw['publishedDate'] ?? $metadata['publishedDate'] ?? null;
            $keywords = $raw['keywords'] ?? $metadata['keywords'] ?? [];

            // Normalize text fields
            $title = $title ? trim(strip_tags($title)) : null;
            $snippet = $snippet ? trim(strip_tags($snippet)) : null;
            $author = $author ? trim(strip_tags($author)) : null;

            // Normalize date to Y-m-d; if date is missing, check if the URL
            // path contains the current year as a proxy for freshness.
            if ($publishedDate) {
                $timestamp = strtotime((string) $publishedDate);
                if ($timestamp !== false) {
                    $publishedDate = date('Y-m-d', $timestamp);

                    // Reject sources older than the cutoff (7 days)
                    $publishedAt = \Carbon\Carbon::parse($publishedDate)->startOfDay();
                    if ($publishedAt->lt($cutoffDate)) {
                        Log::info('SourceCollectionService: Dropping stale source (older than 7 days).', [
                            'url'            => $normalizedUrl,
                            'published_date' => $publishedDate,
                        ]);
                        continue;
                    }
                } else {
                    // Date string was present but unparseable; treat as unknown
                    $publishedDate = null;
                }
            }

            if ($publishedDate === null) {
                // No date at all: check if the URL path contains the current year.
                // If it explicitly contains an older year (e.g. /2022/) reject it.
                if (preg_match('/\/(20\d{2})\//', $normalizedUrl, $yearMatch)) {
                    $urlYear = (int) $yearMatch[1];
                    if ($urlYear < $currentYear) {
                        Log::info('SourceCollectionService: Dropping legacy-year URL (no date, old year in path).', [
                            'url'      => $normalizedUrl,
                            'url_year' => $urlYear,
                        ]);
                        continue;
                    }
                }
                // No date and no year in URL — use today as a conservative default
                // (the source came directly from our grounding query so it is likely fresh)
                $publishedDate = now()->format('Y-m-d');
            }

            // Infer region & locale
            $regionData = $this->inferRegion($normalizedUrl, $title, $snippet, $publisher);

            // Extract keywords if empty
            if (empty($keywords)) {
                $keywords = $this->extractKeywords($title, $snippet);
            } else {
                // Sanitize keyword strings
                $keywords = array_values(array_unique(array_filter(array_map(function ($kw) {
                    return strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $kw)));
                }, (array) $keywords), fn($kw) => strlen($kw) >= 3)));
            }

            // Create temporary DTO to compute relevance
            $dto = new SourceDTO(
                url: $normalizedUrl,
                title: $title,
                snippet: $snippet,
                publisher: $publisher,
                author: $author,
                publishedDate: $publishedDate,
                keywords: $keywords,
                metadata: [
                    'region'     => $regionData['region'],
                    'locale'     => $regionData['locale'],
                    'origin_url' => $originUrl,  // immutably bound at extraction time
                ]
            );

            // Dynamically calculate and set relevance score
            $dto->relevanceScore = $this->calculateRelevance($dto, $queries, $topic);

            $uniqueSources[$normalizedUrl] = $dto;
        }

        // Sort by relevance score descending
        usort($uniqueSources, function (SourceDTO $a, SourceDTO $b) {
            return $b->relevanceScore <=> $a->relevanceScore;
        });

        return $uniqueSources;
    }

    /**
     * Normalize URL helper: lowercase scheme/host, trim spaces, strip trailing slashes, remove default ports.
     */
    public function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!$parts || !isset($parts['scheme']) || !isset($parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if (($scheme === 'http' && $port === ':80') || ($scheme === 'https' && $port === ':443')) {
            $port = '';
        }

        $path = $parts['path'] ?? '';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return "{$scheme}://{$host}{$port}{$path}{$query}{$fragment}";
    }

    /**
     * Infer region/locale if present in the metadata or URL host.
     */
    protected function inferRegion(string $url, ?string $title, ?string $snippet, ?string $publisher): array
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        // Defaults
        $region = 'US';
        $locale = 'en-US';

        if (str_ends_with($host, '.uk') || str_contains($host, '.co.uk')) {
            $region = 'GB';
            $locale = 'en-GB';
        } elseif (str_ends_with($host, '.ca')) {
            $region = 'CA';
            $locale = 'en-CA';
        } elseif (str_ends_with($host, '.au')) {
            $region = 'AU';
            $locale = 'en-AU';
        } elseif (str_ends_with($host, '.de')) {
            $region = 'DE';
            $locale = 'de-DE';
        } elseif (str_ends_with($host, '.fr')) {
            $region = 'FR';
            $locale = 'fr-FR';
        } elseif (str_ends_with($host, '.in')) {
            $region = 'IN';
            $locale = 'en-IN';
        } elseif (str_ends_with($host, '.jp')) {
            $region = 'JP';
            $locale = 'ja-JP';
        } else {
            // Check text clues
            $text = strtolower(($title ?? '') . ' ' . ($snippet ?? '') . ' ' . ($publisher ?? ''));
            if (str_contains($text, 'united kingdom') || str_contains($text, 'london') || str_contains($text, ' bbc')) {
                $region = 'GB';
                $locale = 'en-GB';
            } elseif (str_contains($text, 'germany') || str_contains($text, 'berlin')) {
                $region = 'DE';
                $locale = 'de-DE';
            } elseif (str_contains($text, 'india') || str_contains($text, 'delhi') || str_contains($text, 'mumbai')) {
                $region = 'IN';
                $locale = 'en-IN';
            } elseif (str_contains($text, 'canada') || str_contains($text, 'toronto')) {
                $region = 'CA';
                $locale = 'en-CA';
            } elseif (str_contains($text, 'australia') || str_contains($text, 'sydney')) {
                $region = 'AU';
                $locale = 'en-AU';
            }
        }

        return ['region' => $region, 'locale' => $locale];
    }

    /**
     * Extract keywords from text.
     */
    protected function extractKeywords(?string $title, ?string $snippet): array
    {
        $text = ($title ?? '') . ' ' . ($snippet ?? '');
        $words = preg_split('/[\s,\.\?\!\-\(\)\:\;\"\']+/u', strtolower($text));
        if ($words === false) {
            return [];
        }

        $stopWords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'with', 'of', 'is', 'are', 'was', 'were', 'be', 'this', 'that', 'it', 'from', 'by', 'their', 'our', 'your', 'my', 'his', 'her', 'its', 'about', 'more', 'some', 'has', 'have', 'had', 'been', 'will', 'would', 'should', 'could', 'can', 'may', 'might', 'must', 'these', 'those', 'then', 'than', 'there', 'also', 'other', 'another', 'into', 'over', 'under'
        ];

        $keywords = [];
        foreach ($words as $word) {
            $word = trim($word);
            $word = preg_replace('/[^a-zA-Z0-9]/', '', $word);
            if ($word !== null && strlen($word) >= 3 && !in_array($word, $stopWords, true)) {
                $keywords[] = $word;
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Dynamically calculate relevance score for a source DTO.
     */
    protected function calculateRelevance(SourceDTO $dto, array $queries, string $topic): float
    {
        // 1. Gather all terms from queries and topic
        $searchTerms = [];
        $allQueries = array_merge($queries, [$topic]);
        foreach ($allQueries as $q) {
            $words = preg_split('/[\s,\.\?\!\-]+/u', strtolower($q));
            if ($words !== false) {
                foreach ($words as $w) {
                    $w = trim(preg_replace('/[^a-zA-Z0-9]/', '', $w));
                    if (strlen($w) >= 3) {
                        $searchTerms[] = $w;
                    }
                }
            }
        }
        $searchTerms = array_unique($searchTerms);

        // 2. Compute keyword match density
        $titleAndSnippet = strtolower(($dto->title ?? '') . ' ' . ($dto->snippet ?? ''));
        $matchedCount = 0;
        foreach ($searchTerms as $term) {
            if (str_contains($titleAndSnippet, $term)) {
                $matchedCount++;
            }
        }

        $relevanceDensity = empty($searchTerms) ? 0.0 : $matchedCount / count($searchTerms);

        // 3. Compute metadata completeness
        $fields = [
            $dto->url,
            $dto->title,
            $dto->snippet,
            $dto->publisher,
            $dto->author,
            $dto->publishedDate,
        ];
        $nonEmptyFields = 0;
        foreach ($fields as $field) {
            if ($field !== null && $field !== '') {
                $nonEmptyFields++;
            }
        }
        if (!empty($dto->keywords)) {
            $nonEmptyFields++;
        }
        $completeness = $nonEmptyFields / 7.0;

        // Dynamic formula
        $score = ($relevanceDensity * 0.7) + ($completeness * 0.3);

        return round($score, 3);
    }

    /**
     * Cluster topics based on keyword frequency and overlap.
     */
    protected function clusterTopics(array $sources): array
    {
        $clusters = [];
        foreach ($sources as $source) {
            foreach ($source->keywords as $keyword) {
                $clusters[$keyword][] = $source->url;
            }
        }

        $significantClusters = [];
        foreach ($clusters as $tag => $urls) {
            $uniqueUrls = array_values(array_unique($urls));
            if (count($uniqueUrls) >= 2) {
                $significantClusters[$tag] = $uniqueUrls;
            }
        }

        // Fallback to top keywords if no multi-source clusters exist
        if (empty($significantClusters)) {
            foreach ($clusters as $tag => $urls) {
                $uniqueUrls = array_values(array_unique($urls));
                if (count($uniqueUrls) >= 1) {
                    $significantClusters[$tag] = $uniqueUrls;
                }
            }
        }

        // Sort by count of sources descending
        uasort($significantClusters, fn($a, $b) => count($b) <=> count($a));

        return array_slice($significantClusters, 0, 5, true);
    }


    /**
     * Perform real web search using the pipeline's AI provider.
     * Uses Gemini's Google Search grounding tool when available.
     * Non-Gemini providers route through searchViaPrompt() only in the test
     * environment — in production, the handle() method blocks non-grounded
     * providers before this is reached.
     *
     * @param string $query The search query
     * @param string $topic The news topic/category
     * @param string $providerKey The AI provider key (e.g. 'gemini')
     * @param string $apiKey The decrypted API key
     * @param string|null $model The model name
     * @return array<int, array> Normalized source arrays
     */
    public function searchWithProvider(
        string $query,
        string $topic,
        string $providerKey,
        string $apiKey,
        ?string $model = null
    ): array {
        try {
            if (strtolower($providerKey) === 'gemini') {
                return $this->searchViaGeminiGrounding($query, $topic, $apiKey, $model);
            }

            return $this->searchViaPrompt($query, $topic, $providerKey, $apiKey, $model);
        } catch (\Exception $e) {
            Log::warning('SourceCollectionService: Real search failed, skipping query.', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Use Gemini's native Google Search grounding to find current news sources.
     *
     * The grounding prompt includes an explicit "after:YYYY-MM-DD" date constraint
     * to bias Gemini Search toward today's results.
     *
     * After extracting grounding chunk URIs, this method fires real HTTP GET
     * requests to each article page to extract a rich snippet and publish date:
     *   - og:description / meta[name=description] → rich multi-sentence snippet
     *   - article:published_time / <time datetime>  → real publish date
     *   - First <p> body text                       → last-resort snippet fallback
     *
     * Each grounding chunk's snippet is derived solely from its own title/URI.
     */
    protected function searchViaGeminiGrounding(
        string $query,
        string $topic,
        string $apiKey,
        ?string $model = null
    ): array {
        $model = $model ?: 'gemini-2.5-flash';
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $today    = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        // Add explicit date constraint so Gemini prioritises today's results.
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => "Search for the latest news about: {$query} after:{$yesterday}. List the top 3 most relevant, real, current news sources published today or within the last 48 hours (after {$yesterday}). For each source include its URL, headline title, and a 1-sentence summary. Focus strictly on news from {$today} or {$yesterday}."],
                    ],
                ],
            ],
            'tools' => [
                ['googleSearch' => (object) []],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 1024,
                'temperature'     => 0.1,
            ],
        ];

        $response = Http::timeout(30)->post($url, $payload);

        if (!$response->successful()) {
            Log::warning('Gemini grounding search failed', ['status' => $response->status(), 'query' => $query]);
            return [];
        }

        $data = $response->json();

        // Extract grounding metadata (web search results)
        $groundingChunks = $data['candidates'][0]['groundingMetadata']['groundingChunks'] ?? [];
        $sources = [];

        foreach ($groundingChunks as $chunk) {
            $web = $chunk['web'] ?? null;
            if (!$web || empty($web['uri'])) {
                continue;
            }

            // Each source's publisher and snippet are derived exclusively from this
            // chunk's own URI and title, never from Gemini's generated prose.
            $sourceUri       = $web['uri'];
            $sourceTitle     = $web['title'] ?? $topic . ' News';
            $sourcePublisher = preg_replace('/^www\./', '', strtolower(parse_url($sourceUri, PHP_URL_HOST) ?? 'Unknown'));

            // Fetch real article content to extract a rich snippet and the actual publish date.
            [$richSnippet, $publishedDate] = $this->fetchArticleMetadata($sourceUri, $sourceTitle, $today);

            $sources[] = [
                'url'      => $sourceUri,
                'title'    => $sourceTitle,
                'snippet'  => $richSnippet,
                'metadata' => [
                    'query'          => $query,
                    'publisher'      => $sourcePublisher,
                    'published_date' => $publishedDate,
                    'keywords'       => array_filter(explode(' ', strtolower($topic))),
                    'origin'         => 'gemini_grounding',
                    'origin_url'     => $sourceUri,  // immutably bound at extraction time
                ],
            ];
        }

        return $sources;
    }

    /**
     * Fetch real article metadata from a URL.
     *
     * Extracts og:description / meta[name=description] for a rich multi-sentence
     * snippet, and article:published_time for the real publish date.
     * Falls back gracefully to the title and today's date if the fetch fails.
     *
     * @return array{0: string, 1: string}  [snippet, publishedDate]
     */
    private function fetchArticleMetadata(string $url, string $fallbackTitle, string $todayIso): array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NewsBlogifyBot/1.0)'])
                ->get($url);

            if (!$response->successful()) {
                return [$fallbackTitle, $todayIso];
            }

            $html = $response->body();

            // ── Published date: article:published_time (most reliable) ─────────
            $publishedDate = $todayIso;
            if (preg_match('/<meta[^>]+property=["\']article:published_time["\'][^>]+content=["\']([\d\-T:+Z]+)["\'][^>]*>/i', $html, $m)
                || preg_match('/<meta[^>]+content=["\']([\d\-T:+Z]+)["\'][^>]+property=["\']article:published_time["\']/i', $html, $m)) {
                try {
                    $publishedDate = \Carbon\Carbon::parse($m[1])->format('Y-m-d');
                } catch (\Throwable) {
                    // Keep today as default
                }
            } elseif (preg_match('/<time[^>]+datetime=["\']([\d\-T:+Z]+)["\']/i', $html, $m)) {
                try {
                    $publishedDate = \Carbon\Carbon::parse($m[1])->format('Y-m-d');
                } catch (\Throwable) {
                    // Keep today
                }
            }

            // ── Rich snippet: og:description (best for news articles) ─────────
            $snippet = null;
            if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\'][^>]*>/is', $html, $m)
                || preg_match('/<meta[^>]+content=["\'](.*?)["\'][^>]+property=["\']og:description["\']/is', $html, $m)) {
                $snippet = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            // Fallback: meta[name=description]
            if (empty($snippet)) {
                if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\'][^>]*>/is', $html, $m)
                    || preg_match('/<meta[^>]+content=["\'](.*?)["\'][^>]+name=["\']description["\']/is', $html, $m)) {
                    $snippet = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
            }

            // Fallback: first <p> tag with meaningful text (≥ 60 chars)
            if (empty($snippet)) {
                if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $paragraphs)) {
                    foreach ($paragraphs[1] as $para) {
                        $clean = trim(html_entity_decode(strip_tags($para), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        if (mb_strlen($clean) >= 60) {
                            $snippet = mb_substr($clean, 0, 400);
                            break;
                        }
                    }
                }
            }

            // Last resort: use the title
            if (empty($snippet)) {
                $snippet = $fallbackTitle;
            }

            return [mb_substr($snippet, 0, 600), $publishedDate];

        } catch (\Throwable $e) {
            Log::debug('SourceCollectionService: fetchArticleMetadata failed (non-blocking).', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return [$fallbackTitle, $todayIso];
        }
    }

    /**
     * Scrape the real body text of a news article URL for use as grounded facts
     * during article generation.
     *
     * Strips boilerplate HTML (nav, header, footer, aside, scripts, ads) and
     * extracts clean paragraph text. Returns up to 2500 characters — enough to
     * anchor the AI in real reported facts without overwhelming the token budget.
     *
     * Falls back to empty string if the page is paywalled, blocked, non-HTML,
     * or times out. The caller must handle the empty-string case gracefully
     * (write a shorter, honest article rather than padding with hallucinations).
     *
     * @param string $url The article URL to scrape
     * @return string Cleaned paragraph text, max 2500 chars, or '' on failure
     */
    public function scrapeArticleBody(string $url): string
    {
        // Skip non-HTTP URLs and Vertex redirect URLs (they never return HTML)
        if (! str_starts_with($url, 'http') || str_contains($url, 'vertexaisearch.cloud.google.com')) {
            return '';
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-IN,en;q=0.9,hi;q=0.8',
                ])
                ->get($url);

            // Only process successful HTML responses
            if (! $response->successful()) {
                Log::debug('SourceCollectionService::scrapeArticleBody: non-200 response.', [
                    'url'    => $url,
                    'status' => $response->status(),
                ]);
                return '';
            }

            $contentType = $response->header('Content-Type') ?? '';
            if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'text/plain')) {
                return '';
            }

            $html = $response->body();

            // Prefer structured NewsArticle data when available. Many modern
            // sites render the visible body client-side and leave no useful
            // <p> tags in the initial HTML, but expose articleBody in JSON-LD.
            $structuredBody = $this->extractStructuredArticleBody($html);

            // ── Remove boilerplate blocks entirely ────────────────────────────
            // Strip scripts, styles, nav, footer, header, sidebar, forms, ads
            $html = preg_replace(
                '/<(script|style|nav|footer|header|aside|form|noscript|figure|figcaption|iframe|button|select|textarea)[^>]*>.*?<\/\1>/is',
                '',
                $html
            ) ?? $html;

            // Remove HTML comments
            $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;

            // ── Extract <p> tag text ──────────────────────────────────────────
            $paragraphs = [];
            if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches)) {
                foreach ($matches[1] as $raw) {
                    // Decode entities and strip inline tags
                    $clean = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    // Skip nav links, captions, labels (too short or looks like UI text)
                    if (mb_strlen($clean) < 40) {
                        continue;
                    }

                    // Skip boilerplate sentences common in news sites
                    $lowerClean = strtolower($clean);
                    if (
                        str_contains($lowerClean, 'subscribe') ||
                        str_contains($lowerClean, 'advertisement') ||
                        str_contains($lowerClean, 'cookie') ||
                        str_contains($lowerClean, 'sign up') ||
                        str_contains($lowerClean, 'follow us') ||
                        str_contains($lowerClean, 'read more') ||
                        str_contains($lowerClean, 'click here') ||
                        str_contains($lowerClean, 'also read')
                    ) {
                        continue;
                    }

                    $paragraphs[] = $clean;
                }
            }

            if (empty($paragraphs)) {
                return $structuredBody;
            }

            // Join paragraphs with double newlines and cap at 2500 chars
            $body = implode("\n\n", $paragraphs);
            return mb_substr($body, 0, 2500);

        } catch (\Throwable $e) {
            Log::debug('SourceCollectionService::scrapeArticleBody: fetch failed (non-blocking).', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function extractStructuredArticleBody(string $html): string
    {
        if (! preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            return '';
        }

        $findBody = function (mixed $node) use (&$findBody): ?string {
            if (! is_array($node)) {
                return null;
            }
            if (isset($node['articleBody']) && is_string($node['articleBody'])) {
                $body = trim(html_entity_decode(strip_tags($node['articleBody']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (mb_strlen($body) >= 80) {
                    return $body;
                }
            }
            foreach ($node as $value) {
                if (($body = $findBody($value)) !== null) {
                    return $body;
                }
            }

            return null;
        };

        foreach ($matches[1] as $json) {
            $decoded = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (($body = $findBody($decoded)) !== null) {
                return mb_substr($body, 0, 5000);
            }
        }

        return '';
    }

    /**
     * For non-Gemini providers: use a structured prompt to extract source references.
     */
    protected function searchViaPrompt(
        string $query,
        string $topic,
        string $providerKey,
        string $apiKey,
        ?string $model = null
    ): array {
        $today = now()->format('F j, Y');
        $prompt = "You are a news research assistant. Today is {$today}.\n\n"
            . "Search your knowledge for the top 3 most CURRENT real news sources about: {$query}\n\n"
            . "Respond ONLY with a valid JSON array (no markdown) of exactly 3 objects, each with:\n"
            . '{"url": "https://...", "title": "...", "snippet": "...", "publisher": "..."}' . "\n\n"
            . "Focus only on REAL, CURRENT events from the last 48-72 hours. Use real, working URLs from major news outlets.";

        $driver = $this->providerService->getDriver($providerKey);
        $result = $driver->generate($apiKey, $prompt, $model, [
            'max_tokens' => 512,
            'temperature' => 0.1,
            'task' => 'search_source',
        ]);
        $text   = trim($result['text'] ?? '');

        // Strip markdown fences
        $text = preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text;
        $start = strpos($text, '[');
        $end   = strrpos($text, ']');
        if ($start === false || $end === false) {
            return [];
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (!is_array($decoded)) {
            return [];
        }

        $sources = [];
        foreach ($decoded as $item) {
            if (empty($item['url'])) {
                continue;
            }
            $sources[] = [
                'url'     => $item['url'],
                'title'   => $item['title'] ?? $topic . ' news',
                'snippet' => $item['snippet'] ?? '',
                'metadata' => [
                    'query'          => $query,
                    'publisher'      => $item['publisher'] ?? parse_url($item['url'], PHP_URL_HOST),
                    'published_date' => now()->format('Y-m-d'),
                    'keywords'       => array_filter(explode(' ', strtolower($topic))),
                    'origin'         => 'prompt_search',
                ],
            ];
        }

        return $sources;
    }
}
