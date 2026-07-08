<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\Models\NewsCandidate;
use Illuminate\Support\Str;

/**
 * Enforces news uniqueness across the newsroom workflow.
 *
 * Consumers: NewsDiscoveryService (candidate filtering), candidate
 * selection re-check, and (future) pre-publish gate. Heuristic today
 * (normalized title similarity + keyword Jaccard overlap); designed so
 * embeddings/semantic similarity can replace the internals without any
 * caller changing.
 */
class DuplicateDetectionService
{
    /** Normalized title similarity above which two items are duplicates. */
    public const TITLE_SIMILARITY_THRESHOLD = 0.72;

    /** Keyword Jaccard overlap above which two items are duplicates. */
    public const KEYWORD_OVERLAP_THRESHOLD = 0.60;

    /** How far back to compare against published/generated history. */
    public const HISTORY_WINDOW_DAYS = 90;

    /** Cap on history rows loaded for comparison. */
    public const HISTORY_LIMIT = 300;

    /**
     * Check a single headline against site history and extra titles.
     *
     * @param array<int, string> $keywords
     * @param array<int, array{title: string, keywords?: array}> $comparisons additional in-memory items (e.g. sibling candidates)
     */
    public function isDuplicate(string $title, array $keywords, int $siteId, array $comparisons = []): bool
    {
        $hash = NewsCandidate::hashTitle($title);

        foreach ($this->historyItems($siteId) as $item) {
            if ($item['hash'] === $hash) {
                return true;
            }
            if ($this->isPairDuplicate($title, $keywords, $item['title'], $item['keywords'])) {
                return true;
            }
        }

        foreach ($comparisons as $item) {
            if ($this->isPairDuplicate($title, $keywords, (string) ($item['title'] ?? ''), (array) ($item['keywords'] ?? []))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Split raw candidate payloads into unique and duplicate sets.
     *
     * Uniqueness is evaluated against site history AND previously accepted
     * items within the same batch, so the returned unique set never
     * overlaps itself.
     *
     * @param array<int, array{title: string, keywords?: array}> $candidates
     * @return array{unique: array<int, array>, duplicates: array<int, array>}
     */
    public function filterUnique(array $candidates, int $siteId): array
    {
        $unique = [];
        $duplicates = [];

        foreach ($candidates as $candidate) {
            $title = trim((string) ($candidate['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $keywords = array_values(array_filter(array_map('strval', (array) ($candidate['keywords'] ?? []))));

            if ($this->isDuplicate($title, $keywords, $siteId, $unique)) {
                $duplicates[] = $candidate;
            } else {
                $unique[] = $candidate;
            }
        }

        return ['unique' => $unique, 'duplicates' => $duplicates];
    }

    /**
     * Normalized similarity between two headlines (0.0 - 1.0).
     */
    public function titleSimilarity(string $a, string $b): float
    {
        $a = $this->normalize($a);
        $b = $this->normalize($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        return round($percent / 100, 4);
    }

    /**
     * Jaccard overlap between two keyword sets (0.0 - 1.0).
     */
    public function keywordOverlap(array $a, array $b): float
    {
        $a = array_unique(array_map(fn ($k) => $this->normalize((string) $k), $a));
        $b = array_unique(array_map(fn ($k) => $this->normalize((string) $k), $b));
        $a = array_filter($a);
        $b = array_filter($b);

        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union > 0 ? round($intersection / $union, 4) : 0.0;
    }

    protected function isPairDuplicate(string $titleA, array $keywordsA, string $titleB, array $keywordsB): bool
    {
        if ($titleB === '') {
            return false;
        }

        if ($this->titleSimilarity($titleA, $titleB) >= self::TITLE_SIMILARITY_THRESHOLD) {
            return true;
        }

        return $this->keywordOverlap($keywordsA, $keywordsB) >= self::KEYWORD_OVERLAP_THRESHOLD;
    }

    /**
     * Recent comparison corpus for a site: generated/published article titles
     * plus previously selected news candidates.
     *
     * @return array<int, array{title: string, keywords: array, hash: string}>
     */
    protected function historyItems(int $siteId): array
    {
        $since = now()->subDays(self::HISTORY_WINDOW_DAYS);

        $articleTitles = GeneratedContent::query()
            ->where('site_id', $siteId)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(self::HISTORY_LIMIT)
            ->pluck('title');

        $selectedCandidates = NewsCandidate::query()
            ->join('pipeline_runs', 'news_candidates.pipeline_run_id', '=', 'pipeline_runs.id')
            ->join('content_pipelines', 'pipeline_runs.pipeline_id', '=', 'content_pipelines.id')
            ->where('content_pipelines.site_id', $siteId)
            ->where('news_candidates.status', NewsCandidate::STATUS_SELECTED)
            ->where('news_candidates.created_at', '>=', $since)
            ->orderByDesc('news_candidates.created_at')
            ->limit(self::HISTORY_LIMIT)
            ->get(['news_candidates.title', 'news_candidates.keywords']);

        $items = [];

        foreach ($articleTitles as $title) {
            $items[] = [
                'title' => (string) $title,
                'keywords' => [],
                'hash' => NewsCandidate::hashTitle((string) $title),
            ];
        }

        foreach ($selectedCandidates as $candidate) {
            $items[] = [
                'title' => (string) $candidate->title,
                'keywords' => is_array($candidate->keywords) ? $candidate->keywords : (array) json_decode((string) $candidate->keywords, true),
                'hash' => NewsCandidate::hashTitle((string) $candidate->title),
            ];
        }

        return $items;
    }

    protected function normalize(string $text): string
    {
        return Str::of($text)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
