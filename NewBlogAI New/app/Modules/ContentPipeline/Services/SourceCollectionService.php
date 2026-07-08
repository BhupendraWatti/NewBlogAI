<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\Contracts\SourceCollectorInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\DTOs\SourceDTO;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SourceCollectionService implements SourceCollectorInterface
{
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

            // 2. Gather simulated sources
            foreach ($queries as $query) {
                $simulatedSources = $this->simulateSearch($query, $topic ?? '');
                foreach ($simulatedSources as $source) {
                    $rawSources[] = $source;
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
            Log::error('SourceCollectionService failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            $context->addError('source_collector', $e->getMessage());
        }

        return $context;
    }

    /**
     * Process, normalize, deduplicate, and rank raw source arrays.
     * Returns an array of sorted SourceDTOs.
     */
    protected function processSources(array $rawSources, array $queries, string $topic): array
    {
        $uniqueSources = [];

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

            // Extract fields
            $metadata = $raw['metadata'] ?? [];
            $title = $raw['title'] ?? null;
            $snippet = $raw['snippet'] ?? null;
            $publisher = $raw['publisher'] ?? $metadata['publisher'] ?? null;
            $author = $raw['author'] ?? $metadata['author'] ?? null;
            $publishedDate = $raw['published_date'] ?? $metadata['published_date'] ?? $raw['publishedDate'] ?? $metadata['publishedDate'] ?? null;
            $keywords = $raw['keywords'] ?? $metadata['keywords'] ?? [];

            // Normalize text fields
            $title = $title ? trim(strip_tags($title)) : null;
            $snippet = $snippet ? trim(strip_tags($snippet)) : null;
            $publisher = $publisher ? trim(strip_tags($publisher)) : null;
            $author = $author ? trim(strip_tags($author)) : null;

            // Normalize date to Y-m-d
            if ($publishedDate) {
                $timestamp = strtotime((string) $publishedDate);
                if ($timestamp !== false) {
                    $publishedDate = date('Y-m-d', $timestamp);
                } else {
                    $publishedDate = date('Y-m-d');
                }
            } else {
                $publishedDate = date('Y-m-d');
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
                    'region' => $regionData['region'],
                    'locale' => $regionData['locale'],
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
     * Simulate source collection search results for a query.
     */
    protected function simulateSearch(string $query, string $topic): array
    {
        $slugifiedQuery = Str::slug($query);
        $domain = 'techblog.org';
        if (str_contains(strtolower($query), 'finance') || str_contains(strtolower($query), 'invest')) {
            $domain = 'marketwatchers.com';
        } elseif (str_contains(strtolower($query), 'health') || str_contains(strtolower($query), 'medical')) {
            $domain = 'healthscience.net';
        }

        $sources = [];

        // Source 1 (Standard)
        $sources[] = [
            'url' => "https://www.{$domain}/articles/{$slugifiedQuery}/", // trailing slash to test normalization
            'title' => 'Understanding ' . $topic . ': A Comprehensive Guide (' . now()->format('Y') . ')',
            'snippet' => "This article covers the essential concepts of {$query}. It highlights critical findings, real-world applications, and future trends that professionals must be aware of in " . now()->format('Y') . '.',
            'metadata' => [
                'query' => $query,
                'author' => 'Jane Doe',
                'publisher' => ucfirst(explode('.', $domain)[0]),
                'published_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'keywords' => ['intelligence', 'pipeline', 'source', 'technology']
            ]
        ];

        // Source 2 (Duplicate URL to test deduplication)
        $sources[] = [
            'url' => "HTTPS://WWW.{$domain}/articles/{$slugifiedQuery}", 
            'title' => 'Duplicate - Understanding ' . $topic,
            'snippet' => 'Should be filtered by deduplication.',
            'metadata' => [
                'query' => $query,
                'author' => 'Jane Doe',
                'publisher' => ucfirst(explode('.', $domain)[0]),
                'published_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
            ]
        ];

        // Source 3 (Regional UK source)
        $sources[] = [
            'url' => "https://www.ukblog.co.uk/articles/{$slugifiedQuery}",
            'title' => 'UK Perspective on ' . $topic,
            'snippet' => "A London perspective on {$query}. Discussing how UK companies adopt {$topic} and standardise regional processes.",
            'metadata' => [
                'query' => $query,
                'author' => 'John Smith',
                'publisher' => 'UK Tech Journal',
                'published_date' => now()->subDays(5)->format('Y-m-d'),
                'keywords' => ['intelligence', 'uk', 'london', 'regional']
            ]
        ];

        // Source 4 (German source, high relevance / high keyword density)
        $sources[] = [
            'url' => "https://web.de/science/{$slugifiedQuery}",
            'title' => "Specialized analysis of {$topic} and {$query}",
            'snippet' => "Comprehensive scientific analysis of {$topic} and {$query} in Germany. Details deep tech insights about {$topic} and {$query}.",
            'metadata' => [
                'query' => $query,
                'author' => 'Dr. Fritz',
                'publisher' => 'German Science Web',
                'published_date' => now()->subDays(10)->format('Y-m-d'),
                'keywords' => ['intelligence', 'pipeline', 'germany', 'science']
            ]
        ];

        return $sources;
    }
}
