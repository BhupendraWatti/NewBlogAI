<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

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

    /** Penalty applied when no named source publisher is present. */
    private const NO_NAMED_SOURCE_PENALTY = 20;

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
     * @param array{title: string, summary?: string|null, source_references?: array} $candidate
     */
    public function score(array $candidate): int
    {
        $score   = 100;
        $reasons = [];

        $title   = strtolower((string) ($candidate['title'] ?? ''));
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
        $sources        = (array) ($candidate['source_references'] ?? []);
        $hasNamedSource = collect($sources)->contains(function ($src) {
            return ! empty($src['name']) && trim((string) $src['name']) !== '';
        });

        if (! $hasNamedSource) {
            $score -= self::NO_NAMED_SOURCE_PENALTY;
            $reasons[] = 'no_named_source';
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
                'title'   => mb_substr((string) ($candidate['title'] ?? ''), 0, 80),
                'score'   => $finalScore,
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
        $passed   = [];
        $rejected = [];

        foreach ($candidates as $candidate) {
            $qualityScore              = $this->score($candidate);
            $candidate['quality_score'] = $qualityScore;

            if ($qualityScore >= self::MIN_QUALITY_SCORE) {
                $passed[] = $candidate;
            } else {
                $rejected[] = $candidate;
                Log::info('ContentQualityFilterService: candidate rejected.', [
                    'title' => mb_substr((string) ($candidate['title'] ?? ''), 0, 80),
                    'score' => $qualityScore,
                ]);
            }
        }

        return ['passed' => $passed, 'rejected' => $rejected];
    }
}
