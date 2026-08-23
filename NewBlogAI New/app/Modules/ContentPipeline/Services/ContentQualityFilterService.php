<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Issue #1 Fix - Sub-problem 2: Low-Quality Gossip / Trash Filter.
 *
 * Scores every incoming news candidate (0-100) and rejects those that
 * fall below the configured threshold. Runs BEFORE DB persistence so
 * gossip, TikTok/Reel rumours, sensational local stories, and minor
 * domestic crimes never enter the candidate list.
 *
 * Design principles:
 *  - Score-based, NOT blacklist-based - avoids fragile keyword lists.
 *  - Config-driven thresholds (per-workspace override is future-ready).
 *  - Stateless - can be injected anywhere in the pipeline.
 *  - Transparent - every rejection is logged with its score and reasons.
 */
class ContentQualityFilterService
{
    /**
     * Minimum quality score (0-100) a candidate must achieve to pass.
     * Candidates scoring below this are rejected as low quality.
     *
     * This can be promoted to a per-pipeline/per-workspace DB setting
     * in a future iteration without changing any caller code.
     */
    public const MIN_QUALITY_SCORE = 45;

    /** Penalty applied when a gossip/trash signal is matched in the title. */
    private const GOSSIP_TITLE_PENALTY = 20;

    /** Penalty applied when a gossip signal is found only in the summary. */
    private const GOSSIP_SUMMARY_PENALTY = 10;

    /**
     * Bug Fix #5a: Reduced from 20 → 10. Gemini grounded search results
     * legitimately strip publisher names from source_references, so a 20-point
     * penalty was causing real, credible news to be incorrectly rejected.
     */
    private const NO_NAMED_SOURCE_PENALTY = 10;

    /**
     * Bug Fix #5b: Penalty for source_references that only contain Google
     * redirect/grounding URLs (google.com/grounding-api-redirect/...) without
     * a real publisher URL. These are proxy redirects, not verifiable sources.
     */
    private const REDIRECT_URL_ONLY_PENALTY = 15;

    /**
     * Penalty applied when the candidate summary is too thin (fewer than
     * MIN_SUMMARY_WORDS words), which is typical of gossip snippets.
     */
    private const THIN_CONTENT_PENALTY = 15;

    /** Minimum word count for a summary to not be penalised as thin. */
    private const MIN_SUMMARY_WORDS = 20;

    /**
     * Gossip / trash signals matched against the candidate title.
     * Kept deliberately short - the scoring model does the heavy lifting.
     */
    private const GOSSIP_TITLE_SIGNALS = [
        'tiktok',
        'reel',
        'viral video',
        'viral clip',
        'dance video',
        'dancing at party',
        'mms',
        'sex tape',
        'obscene video',
        'rumour',
        'rumor',
        'gossip',
        'insider claim',
        'sources claim',
        'sources say',
        'unverified',
    ];

    /**
     * Minor-crime / domestic signals. Stories where these appear in the
     * title are low editorial value for a news platform.
     */
    private const MINOR_CRIME_TITLE_SIGNALS = [
        'petty theft',
        'minor theft',
        'domestic quarrel',
        'domestic brawl',
        'domestic dispute',
        'local brawl',
        'eve teasing',
        'chain snatching',
        'mobile snatching',
        'bike theft',
    ];

    /**
     * Score a single candidate array and return the quality score (0-100).
     *
     * @param  array{title: string, summary?: string|null, source_references?: array}  $candidate
     */
    public function score(array $candidate): int
    {
        $score = 100;
        $reasons = [];

        $title = strtolower((string) ($candidate['title'] ?? ''));
        $summary = strtolower((string) ($candidate['summary'] ?? ''));

        // -- Gossip signals in title (heavier penalty) ----------------------
        foreach (self::GOSSIP_TITLE_SIGNALS as $signal) {
            if (str_contains($title, $signal)) {
                $score -= self::GOSSIP_TITLE_PENALTY;
                $reasons[] = "gossip_title:{$signal}";
            }
        }

        // -- Minor-crime signals in title -----------------------------------
        foreach (self::MINOR_CRIME_TITLE_SIGNALS as $signal) {
            if (str_contains($title, $signal)) {
                $score -= self::GOSSIP_TITLE_PENALTY;
                $reasons[] = "minor_crime_title:{$signal}";
            }
        }

        // -- Gossip signals in summary only (lighter penalty) ---------------
        foreach (self::GOSSIP_TITLE_SIGNALS as $signal) {
            if (! str_contains($title, $signal) && str_contains($summary, $signal)) {
                $score -= self::GOSSIP_SUMMARY_PENALTY;
                $reasons[] = "gossip_summary:{$signal}";
            }
        }

        // -- No named source publisher --------------------------------------
        $sources = (array) ($candidate['source_references'] ?? []);
        $hasNamedSource = collect($sources)->contains(function ($src) {
            return ! empty($src['name']) && trim((string) $src['name']) !== '';
        });

        // Bug Fix #5a: Use the reduced penalty so Gemini-grounded candidates
        // are not incorrectly rejected for missing publisher name labels.
        if (! $hasNamedSource) {
            $score -= self::NO_NAMED_SOURCE_PENALTY;
            $reasons[] = 'no_named_source';
        }

        // Bug Fix #5b: Penalize candidates whose ONLY source URLs are Google
        // grounding redirect proxies — these cannot be directly verified.
        $hasRealUrl = collect($sources)->contains(function ($src) {
            $url = (string) ($src['url'] ?? '');

            return str_starts_with($url, 'http')
                && ! str_contains($url, 'google.com/grounding-api-redirect')
                && ! str_contains($url, 'google.com/search?');
        });
        if (! empty($sources) && ! $hasRealUrl) {
            $score -= self::REDIRECT_URL_ONLY_PENALTY;
            $reasons[] = 'redirect_url_only';
        }

        // -- Thin summary (gossip tends to be shallow) ----------------------
        $wordCount = str_word_count($summary);
        if ($wordCount < self::MIN_SUMMARY_WORDS) {
            $score -= self::THIN_CONTENT_PENALTY;
            $reasons[] = "thin_summary:{$wordCount}_words";
        }

        $finalScore = max(0, $score);

        if (! empty($reasons)) {
            Log::debug('ContentQualityFilterService: candidate penalised.', [
                'title' => mb_substr((string) ($candidate['title'] ?? ''), 0, 80),
                'score' => $finalScore,
                'reasons' => $reasons,
            ]);
        }

        return $finalScore;
    }

    /**
     * Determine whether a candidate passes the quality gate.
     */
    public function passes(array $candidate): bool
    {
        return $this->score($candidate) >= self::MIN_QUALITY_SCORE;
    }

    /**
     * Filter a batch of candidates, returning only those that pass.
     * Attaches the computed quality_score to every surviving candidate.
     *
     * @param  array<int, array>  $candidates
     * @return array{passed: array<int, array>, rejected: array<int, array>}
     */
    public function filter(array $candidates): array
    {
        $passed = [];
        $rejected = [];

        foreach ($candidates as $candidate) {
            $qualityScore = $this->score($candidate);
            $candidate['quality_score'] = $qualityScore;

            $freshnessReason = $this->freshnessRejectionReason($candidate);
            if ($qualityScore >= self::MIN_QUALITY_SCORE && $freshnessReason === null) {
                $passed[] = $candidate;
            } else {
                if ($freshnessReason !== null) {
                    $candidate['quality_rejection_reason'] = $freshnessReason;
                }
                $rejected[] = $candidate;
                Log::info('ContentQualityFilterService: candidate rejected.', [
                    'title' => mb_substr((string) ($candidate['title'] ?? ''), 0, 80),
                    'score' => $qualityScore,
                    'reason' => $freshnessReason ?? 'quality_score',
                ]);
            }
        }

        return ['passed' => $passed, 'rejected' => $rejected];
    }

    private function freshnessRejectionReason(array $candidate): ?string
    {
        $eventDate = trim((string) ($candidate['event_date'] ?? ''));
        $relative = trim((string) ($candidate['published_at_relative'] ?? ''));

        if ($eventDate === '' && $relative === '') {
            return 'freshness_unverified';
        }

        if ($relative !== '' && preg_match('/(\d+)\s*(min|minute|hour|hr|day|week|month)/i', $relative, $matches)) {
            $value = (int) $matches[1];
            $unit = strtolower($matches[2]);
            $hours = match (true) {
                str_starts_with($unit, 'min') => $value / 60,
                str_starts_with($unit, 'h') => $value,
                str_starts_with($unit, 'd') => $value * 24,
                str_starts_with($unit, 'w') => $value * 24 * 7,
                str_starts_with($unit, 'm') => $value * 24 * 30,
                default => null,
            };
            if ($hours !== null && $hours > 48) {
                return 'outside_48_hour_window';
            }
        }

        if ($eventDate !== '') {
            try {
                $date = Carbon::parse($eventDate)->startOfDay();
                $today = now()->startOfDay();
                if ($date->gt($today) || $date->lt($today->copy()->subDay())) {
                    return $date->gt($today) ? 'future_event' : 'outside_48_hour_window';
                }
            } catch (\Throwable) {
                return 'invalid_event_date';
            }
        }

        return null;
    }
}
