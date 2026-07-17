<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use Illuminate\Support\Facades\Log;

/**
 * Issue #1 Fix - Sub-problem 3: Geographic Clumping Enforcer.
 *
 * Prevents the "9 stories from Ujjain" bubble by enforcing per-city
 * and per-state quotas on the candidate batch BEFORE DB persistence.
 *
 * Works in two modes:
 *  1. filter()       - processes a full batch at once (used in discovery).
 *  2. allowArticle() - stateful incremental check for streaming ingestion.
 *
 * Design principles:
 *  - Stateless batch API (filter) is the primary interface.
 *  - Stateful API (allowArticle) is provided for streaming/scraper use.
 *  - Quotas are constants today; can be promoted to per-workspace DB config without any caller changes.
 *  - No external dependencies - pure PHP logic.
 */
class GeographicDiversityEnforcer
{
    /**
     * Maximum articles from the same city allowed in one batch.
     * e.g. max 2 stories from "Ujjain" per discovery run.
     */
    public const MAX_PER_CITY = 2;

    /**
     * Maximum articles from the same state allowed in one batch.
     * e.g. max 4 stories from "Madhya Pradesh" per discovery run.
     */
    public const MAX_PER_STATE = 4;

    /** Internal city counter for stateful (streaming) usage. */
    private array $cityCount = [];

    /** Internal state counter for stateful (streaming) usage. */
    private array $stateCount = [];

    /**
     * Filter a full candidate batch enforcing per-city and per-state quotas.
     *
     * Candidates without geo data (geo_city = null, geo_state = null) are
     * treated as national/international stories and are NEVER blocked by
     * the geo filter.
     *
     * @param  array<int, array>  $candidates  Each item must have optional keys: geo_city, geo_state, title.
     * @return array{passed: array<int, array>, blocked: array<int, array>}
     */
    public function filter(array $candidates): array
    {
        $cityCount  = [];
        $stateCount = [];
        $passed     = [];
        $blocked    = [];

        foreach ($candidates as $candidate) {
            $city  = $this->normalizeGeo((string) ($candidate['geo_city'] ?? ''));
            $state = $this->normalizeGeo((string) ($candidate['geo_state'] ?? ''));

            // No geo data = national/international story, always allow
            if ($city === null && $state === null) {
                $passed[] = $candidate;
                continue;
            }

            $cityCount[$city]   = ($cityCount[$city] ?? 0);
            $stateCount[$state] = ($stateCount[$state] ?? 0);

            $cityBlocked  = $city !== null && $cityCount[$city] >= self::MAX_PER_CITY;
            $stateBlocked = $state !== null && $stateCount[$state] >= self::MAX_PER_STATE;

            if ($cityBlocked || $stateBlocked) {
                $blocked[] = $candidate;
                Log::info('GeographicDiversityEnforcer: candidate blocked by geo quota.', [
                    'title'       => mb_substr((string) ($candidate['title'] ?? ''), 0, 80),
                    'geo_city'    => $city,
                    'geo_state'   => $state,
                    'city_count'  => $cityCount[$city] ?? 0,
                    'state_count' => $stateCount[$state] ?? 0,
                    'reason'      => $cityBlocked ? 'city_quota' : 'state_quota',
                ]);
                continue;
            }

            // Candidate passes - increment counters
            if ($city !== null) {
                $cityCount[$city]++;
            }
            if ($state !== null) {
                $stateCount[$state]++;
            }

            $passed[] = $candidate;
        }

        Log::info('GeographicDiversityEnforcer: batch filtered.', [
            'total'   => count($candidates),
            'passed'  => count($passed),
            'blocked' => count($blocked),
        ]);

        return ['passed' => $passed, 'blocked' => $blocked];
    }

    /**
     * Stateful check for streaming/scraper ingestion.
     *
     * Returns true if the article is allowed under current quotas and
     * increments the internal counters. Returns false if blocked.
     * Call reset() between batches.
     */
    public function allowArticle(?string $city, ?string $state): bool
    {
        $city  = $this->normalizeGeo((string) ($city ?? ''));
        $state = $this->normalizeGeo((string) ($state ?? ''));

        // No geo data = always allow
        if ($city === null && $state === null) {
            return true;
        }

        $cityCount  = $this->cityCount[$city] ?? 0;
        $stateCount = $this->stateCount[$state] ?? 0;

        if ($city !== null && $cityCount >= self::MAX_PER_CITY) {
            return false;
        }
        if ($state !== null && $stateCount >= self::MAX_PER_STATE) {
            return false;
        }

        if ($city !== null) {
            $this->cityCount[$city] = $cityCount + 1;
        }
        if ($state !== null) {
            $this->stateCount[$state] = $stateCount + 1;
        }

        return true;
    }

    /**
     * Reset internal stateful counters between batches.
     */
    public function reset(): void
    {
        $this->cityCount  = [];
        $this->stateCount = [];
    }

    /**
     * Normalize a geo string for consistent comparison.
     * Returns null for empty/whitespace-only strings.
     */
    private function normalizeGeo(string $value): ?string
    {
        $cleaned = mb_strtolower(trim($value));

        return $cleaned === '' || $cleaned === 'null' ? null : $cleaned;
    }
}
