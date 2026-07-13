<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\Contracts\ChronologicalContextParserInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ChronologicalContextParser
 *
 * Pipeline Stage: inserted BETWEEN FactExtractionService and ContentGeneratorService.
 *
 * ─── WHY THIS STAGE EXISTS ───────────────────────────────────────────────────
 * The web scraper correctly fetches articles published within the last 24 hours.
 * However, the text body of a fresh article often describes an event that
 * actually happened 3–7 days ago (e.g. "The sewer collapse that occurred on
 * July 8…" published today as a follow-up administrative story).
 *
 * The generation pipeline previously treated the HTML publication timestamp
 * as the event occurrence time, causing the AI writer to frame follow-up
 * stories as breaking news — a factual framing error.
 *
 * ─── WHAT THIS STAGE DOES ────────────────────────────────────────────────────
 * 1. CHRONOLOGICAL CONTEXT PARSING
 *    Scans available text (candidate summary, source snippets) for historical
 *    date references using:
 *    a) Regex patterns for Hindi relative-time phrases
 *       ("5 दिन पहले", "गुरुवार को", "शुक्रवार की रात")
 *    b) Regex patterns for explicit calendar dates in common formats
 *       ("July 8", "8 जुलाई", "08/07/2026", "2026-07-08")
 *    c) Day-of-week anchor resolution (maps weekday names to actual dates
 *       within the last 14 days)
 *    Extracts the EARLIEST referenced date as the `root_event_time`.
 *
 * 2. PUBLISH TIME / EVENT TIME DECOUPLING
 *    Writes two separate timestamps into $context->metadata:
 *    - `source_publish_time`  — when the article was scraped/published (today or recent)
 *    - `root_event_time`      — when the underlying event actually occurred
 *    - `event_time_lag_hours` — numeric gap between the two
 *    - `story_type`           — 'breaking' | 'followup' | 'background'
 *      Rule: if root_event_time is > 48 hours older than source_publish_time →
 *            story_type = 'followup'. Otherwise story_type = 'breaking'.
 *
 * 3. TEMPORAL FRAMING GUARDRAIL (prompt enrichment)
 *    Sets $context->metadata['dynamic_instructions'] with a framing directive
 *    consumed by PromptEngine::compileDynamicInstructions(). When story_type
 *    is 'followup', the AI writer is instructed to:
 *    - Frame the article around the LATEST development (today's news hook)
 *    - Reference the root event as historical context with the correct date
 *    - NOT write as if the entire event happened today
 *
 * ─── SCOPE CONTRACT ──────────────────────────────────────────────────────────
 * - Reads:  $context->metadata['selected_news'], $context->sources
 * - Writes: $context->metadata keys listed above ONLY
 * - Does NOT touch: DB schema, scraping engines, cron jobs, UI, or any other
 *   pipeline stage. Is fully backwards-compatible: if no date is found, the
 *   stage is a transparent no-op.
 */
class ChronologicalContextParser implements ChronologicalContextParserInterface
{
    /**
     * Minimum lag (hours) between source_publish_time and root_event_time
     * before a story is re-classified as 'followup' instead of 'breaking'.
     */
    private const FOLLOWUP_THRESHOLD_HOURS = 48;

    /**
     * ─── HINDI RELATIVE TIME PATTERNS ────────────────────────────────────────
     * Matches phrases like:
     *   "5 दिन पहले", "तीन दिन पहले", "कल", "परसों",
     *   "गुरुवार को", "सोमवार की रात", "शनिवार सुबह"
     */
    private const HINDI_RELATIVE_PATTERNS = [
        // Numeral + दिन/घंटे/सप्ताह पहले
        '/(\d+)\s*दिन\s*पहले/u'       => ['unit' => 'days'],
        '/(\d+)\s*घंटे?\s*पहले/u'     => ['unit' => 'hours'],
        '/(\d+)\s*सप्ताह\s*पहले/u'   => ['unit' => 'weeks'],
        // Word numbers
        '/एक\s*दिन\s*पहले/u'          => ['unit' => 'days', 'value' => 1],
        '/दो\s*दिन\s*पहले/u'          => ['unit' => 'days', 'value' => 2],
        '/तीन\s*दिन\s*पहले/u'         => ['unit' => 'days', 'value' => 3],
        '/चार\s*दिन\s*पहले/u'         => ['unit' => 'days', 'value' => 4],
        '/पाँच\s*दिन\s*पहले/u'        => ['unit' => 'days', 'value' => 5],
        '/छह\s*दिन\s*पहले/u'          => ['unit' => 'days', 'value' => 6],
        '/सात\s*दिन\s*पहले/u'         => ['unit' => 'days', 'value' => 7],
        // Special words
        '/\bकल\b/u'                   => ['unit' => 'days', 'value' => 1],
        '/\bपरसों\b/u'                => ['unit' => 'days', 'value' => 2],
    ];

    /**
     * ─── HINDI DAY-OF-WEEK ANCHORS ───────────────────────────────────────────
     * Maps Hindi weekday names to Carbon day numbers (0=Sunday).
     */
    private const HINDI_WEEKDAY_MAP = [
        'रविवार'   => Carbon::SUNDAY,
        'सोमवार'   => Carbon::MONDAY,
        'मंगलवार'  => Carbon::TUESDAY,
        'बुधवार'   => Carbon::WEDNESDAY,
        'गुरुवार'  => Carbon::THURSDAY,
        'शुक्रवार' => Carbon::FRIDAY,
        'शनिवार'   => Carbon::SATURDAY,
    ];

    /**
     * ─── EXPLICIT CALENDAR DATE PATTERNS ─────────────────────────────────────
     * English and Hindi month names, ISO dates, DD/MM/YYYY etc.
     */
    private const ENGLISH_MONTHS = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
        'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10,
        'nov' => 11, 'dec' => 12,
    ];

    private const HINDI_MONTHS = [
        'जनवरी' => 1, 'फरवरी' => 2, 'मार्च' => 3, 'अप्रैल' => 4,
        'मई' => 5, 'जून' => 6, 'जुलाई' => 7, 'अगस्त' => 8,
        'सितंबर' => 9, 'अक्टूबर' => 10, 'नवंबर' => 11, 'दिसंबर' => 12,
    ];

    /**
     * Process the stage: parse chronological context and enrich PipelineContext.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        try {
            Log::info('ChronologicalContextParser: Starting temporal context analysis.');

            $today = Carbon::now()->startOfDay();

            // ── 1. Collect text corpus from the context ───────────────────────
            $corpus = $this->buildTextCorpus($context);

            // ── 2. Determine source_publish_time ─────────────────────────────
            // This is the article's publication timestamp — the date the scraper
            // found it. Default: today (sources were fetched within 24 hours).
            $sourcePublishTime = $this->resolveSourcePublishTime($context, $today);

            // ── 3. Scan corpus for root event date ────────────────────────────
            $rootEventTime = $this->extractRootEventTime($corpus, $today);

            // ── 4. Compute lag and classify story type ────────────────────────
            $lagHours  = null;
            $storyType = 'breaking'; // Default: treat as current news

            if ($rootEventTime !== null) {
                // Clamp: root_event_time can never be in the future
                if ($rootEventTime->gt($sourcePublishTime)) {
                    $rootEventTime = $sourcePublishTime->copy();
                }

                $lagHours = $sourcePublishTime->diffInHours($rootEventTime, false);
                // diffInHours with false gives negative for past, positive for future
                $absoluteLagHours = abs($lagHours);

                if ($absoluteLagHours >= self::FOLLOWUP_THRESHOLD_HOURS) {
                    $storyType = 'followup';
                } elseif ($absoluteLagHours >= 6) {
                    $storyType = 'background'; // same-day but not immediate
                } else {
                    $storyType = 'breaking';
                }
            }

            // ── 5. Write to context metadata ──────────────────────────────────
            $context->metadata['source_publish_time']  = $sourcePublishTime->toDateTimeString();
            $context->metadata['root_event_time']      = $rootEventTime?->toDateString();
            $context->metadata['event_time_lag_hours'] = $lagHours !== null ? abs($lagHours) : null;
            $context->metadata['story_type']           = $storyType;

            // ── 6. Build temporal framing guardrail for the AI writer ─────────
            $guardrail = $this->buildTemporalFramingGuardrail($storyType, $rootEventTime, $sourcePublishTime);
            if ($guardrail !== null) {
                // Append to existing dynamic_instructions so other stages are not overwritten
                $existing = $context->metadata['dynamic_instructions'] ?? '';
                $context->metadata['dynamic_instructions'] = trim($existing . "\n\n" . $guardrail);
            }

            Log::info('ChronologicalContextParser: Temporal analysis complete.', [
                'story_type'           => $storyType,
                'source_publish_time'  => $context->metadata['source_publish_time'],
                'root_event_time'      => $context->metadata['root_event_time'],
                'event_time_lag_hours' => $context->metadata['event_time_lag_hours'],
                'corpus_length'        => mb_strlen($corpus),
            ]);

        } catch (\Throwable $e) {
            // This stage is non-blocking. Any failure is logged but does NOT
            // stop the rest of the pipeline from running normally.
            Log::error('ChronologicalContextParser failed (non-blocking): ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            $context->metadata['story_type'] ??= 'breaking';
        }

        return $context;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assemble a single text corpus from all available text in the context.
     * Checks the news candidate summary and all scraped source snippets/titles.
     */
    private function buildTextCorpus(PipelineContext $context): string
    {
        $parts = [];

        // Priority: employee-selected candidate title + summary (richest signal)
        $selectedNews = $context->metadata['selected_news'] ?? null;
        if (is_array($selectedNews)) {
            $parts[] = (string) ($selectedNews['title']   ?? '');
            $parts[] = (string) ($selectedNews['summary'] ?? '');
        }

        // Source titles and snippets from SourceCollectionService
        foreach ($context->sources as $source) {
            $parts[] = (string) ($source['title']   ?? '');
            $parts[] = (string) ($source['snippet'] ?? '');
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * Resolve the canonical source publication time.
     * Prefers the earliest published_date across collected sources. Falls back
     * to today when sources carry no date metadata (they were scraped just now).
     */
    private function resolveSourcePublishTime(PipelineContext $context, Carbon $today): Carbon
    {
        $earliest = null;
        foreach ($context->sources as $source) {
            $dateStr = $source['published_date']
                ?? $source['metadata']['published_date']
                ?? null;

            if (empty($dateStr)) {
                continue;
            }

            try {
                $parsed = Carbon::parse((string) $dateStr)->startOfDay();
                if ($earliest === null || $parsed->lt($earliest)) {
                    $earliest = $parsed;
                }
            } catch (\Throwable) {
                // Unparseable — skip
            }
        }

        // Clamp: publication date can never be in the future
        if ($earliest !== null && $earliest->lte($today)) {
            return $earliest;
        }

        return $today->copy();
    }

    /**
     * ── REQUIREMENT 1: Chronological Context Parser ───────────────────────
     *
     * Scans the text corpus for root event date references and returns the
     * EARLIEST date found as a Carbon instance. Returns null if no date
     * reference is found (no-op mode).
     *
     * Detection order (highest precision first):
     *   1. ISO / numeric explicit dates  (2026-07-08, 08/07/2026)
     *   2. English month-name dates      (July 8, 8 July 2026)
     *   3. Hindi month-name dates        (8 जुलाई, जुलाई 8)
     *   4. Hindi day-of-week anchors     (गुरुवार को → maps to actual date)
     *   5. Hindi relative phrases        (5 दिन पहले → now - 5 days)
     */
    private function extractRootEventTime(string $corpus, Carbon $today): ?Carbon
    {
        $candidates = [];

        // ── Pass 1: ISO & numeric explicit dates ─────────────────────────────
        // Matches: 2026-07-08, 2026/07/08
        if (preg_match_all('/\b(20\d{2})[-\/](0[1-9]|1[0-2])[-\/](0[1-9]|[12]\d|3[01])\b/', $corpus, $m)) {
            foreach (array_keys($m[0]) as $i) {
                $candidates[] = Carbon::createSafe((int)$m[1][$i], (int)$m[2][$i], (int)$m[3][$i]);
            }
        }

        // Matches: 08/07/2026 or 8/7/2026 (DD/MM/YYYY)
        if (preg_match_all('/\b(0?[1-9]|[12]\d|3[01])[\/\-](0?[1-9]|1[0-2])[\/\-](20\d{2})\b/', $corpus, $m)) {
            foreach (array_keys($m[0]) as $i) {
                $candidates[] = Carbon::createSafe((int)$m[3][$i], (int)$m[2][$i], (int)$m[1][$i]);
            }
        }

        // ── Pass 2: English month-name dates ─────────────────────────────────
        // Matches: July 8, July 8 2026, 8 July, 8 July 2026
        $englishMonthPattern = implode('|', array_keys(self::ENGLISH_MONTHS));
        if (preg_match_all(
            '/\b(?:(' . $englishMonthPattern . ')\s+(\d{1,2})(?:\s*,?\s*(20\d{2}))?|(\d{1,2})\s+(' . $englishMonthPattern . ')(?:\s+(20\d{2}))?)\b/i',
            $corpus,
            $m
        )) {
            foreach (array_keys($m[0]) as $i) {
                if (!empty($m[1][$i])) {
                    // "July 8" format
                    $monthNum = self::ENGLISH_MONTHS[strtolower($m[1][$i])] ?? null;
                    $day      = (int) $m[2][$i];
                    $year     = !empty($m[3][$i]) ? (int) $m[3][$i] : $today->year;
                } else {
                    // "8 July" format
                    $monthNum = self::ENGLISH_MONTHS[strtolower($m[5][$i])] ?? null;
                    $day      = (int) $m[4][$i];
                    $year     = !empty($m[6][$i]) ? (int) $m[6][$i] : $today->year;
                }
                if ($monthNum && $day >= 1 && $day <= 31) {
                    $c = Carbon::createSafe($year, $monthNum, $day);
                    if ($c instanceof Carbon) {
                        $candidates[] = $c;
                    }
                }
            }
        }

        // ── Pass 3: Hindi month-name dates ────────────────────────────────────
        // Matches: "8 जुलाई", "जुलाई 8", "8 जुलाई 2026"
        foreach (self::HINDI_MONTHS as $hindiMonth => $monthNum) {
            $escaped = preg_quote($hindiMonth, '/');
            // "8 जुलाई" or "8 जुलाई 2026"
            if (preg_match_all('/(\d{1,2})\s*' . $escaped . '(?:\s*(20\d{2}))?/u', $corpus, $m)) {
                foreach (array_keys($m[0]) as $i) {
                    $day  = (int) $m[1][$i];
                    $year = !empty($m[2][$i]) ? (int) $m[2][$i] : $today->year;
                    if ($day >= 1 && $day <= 31) {
                        $c = Carbon::createSafe($year, $monthNum, $day);
                        if ($c instanceof Carbon) {
                            $candidates[] = $c;
                        }
                    }
                }
            }
            // "जुलाई 8" or "जुलाई 8, 2026"
            if (preg_match_all('/' . $escaped . '\s*(\d{1,2})(?:\s*,?\s*(20\d{2}))?/u', $corpus, $m)) {
                foreach (array_keys($m[0]) as $i) {
                    $day  = (int) $m[1][$i];
                    $year = !empty($m[2][$i]) ? (int) $m[2][$i] : $today->year;
                    if ($day >= 1 && $day <= 31) {
                        $c = Carbon::createSafe($year, $monthNum, $day);
                        if ($c instanceof Carbon) {
                            $candidates[] = $c;
                        }
                    }
                }
            }
        }

        // ── Pass 4: Hindi day-of-week anchors ────────────────────────────────
        // "गुरुवार को", "सोमवार की रात", "शनिवार सुबह"
        // Resolve to the most recent past occurrence of that weekday ≤ today.
        foreach (self::HINDI_WEEKDAY_MAP as $hindiDay => $dayNumber) {
            $escaped = preg_quote($hindiDay, '/');
            if (preg_match('/' . $escaped . '/u', $corpus)) {
                // Find the most recent past weekday
                $ref = $today->copy();
                // Walk back at most 14 days to find the matching weekday
                for ($offset = 0; $offset <= 14; $offset++) {
                    if ($ref->copy()->subDays($offset)->dayOfWeek === $dayNumber) {
                        $candidates[] = $ref->copy()->subDays($offset)->startOfDay();
                        break;
                    }
                }
            }
        }

        // ── Pass 5: Hindi relative time phrases ──────────────────────────────
        foreach (self::HINDI_RELATIVE_PATTERNS as $pattern => $config) {
            if (preg_match_all($pattern, $corpus, $m)) {
                foreach (array_keys($m[0]) as $i) {
                    $value = $config['value'] ?? (isset($m[1][$i]) ? (int)$m[1][$i] : 1);
                    $unit  = $config['unit'];
                    try {
                        $date = match ($unit) {
                            'hours'  => $today->copy()->subHours($value),
                            'days'   => $today->copy()->subDays($value)->startOfDay(),
                            'weeks'  => $today->copy()->subWeeks($value)->startOfDay(),
                            default  => null,
                        };
                        if ($date) {
                            $candidates[] = $date;
                        }
                    } catch (\Throwable) {
                        // Skip malformed
                    }
                }
            }
        }

        // ── Filter: discard future dates and dates older than 90 days ─────────
        $cutoffPast = $today->copy()->subDays(90);
        $valid = array_filter($candidates, function ($c) use ($today, $cutoffPast) {
            return $c instanceof Carbon
                && $c->lte($today)       // not in the future
                && $c->gte($cutoffPast); // not unreasonably old
        });

        if (empty($valid)) {
            return null;
        }

        // Return the EARLIEST (oldest) date found — this is most likely the
        // original event, not a subsequent development.
        usort($valid, fn(Carbon $a, Carbon $b) => $a->timestamp <=> $b->timestamp);

        return reset($valid);
    }

    /**
     * ── REQUIREMENT 3: Temporal Framing Guardrail ─────────────────────────
     *
     * Builds the guardrail instruction string injected into the AI writer
     * prompt via PromptEngine::compileDynamicInstructions().
     *
     * Returns null for 'breaking' stories (no extra instruction needed).
     */
    private function buildTemporalFramingGuardrail(
        string $storyType,
        ?Carbon $rootEventTime,
        Carbon $sourcePublishTime
    ): ?string {
        if ($storyType === 'breaking' || $rootEventTime === null) {
            return null;
        }

        $rootDateFormatted    = $rootEventTime->format('F j, Y');         // "July 8, 2026"
        $publishDateFormatted = $sourcePublishTime->format('F j, Y');     // "July 13, 2026"
        $lagDays              = $sourcePublishTime->diffInDays($rootEventTime); // integer days

        if ($storyType === 'followup') {
            return <<<GUARDRAIL
TEMPORAL FRAMING GUARDRAIL — MANDATORY:
This article is a FOLLOW-UP / DEVELOPMENTAL story. The underlying incident occurred on {$rootDateFormatted} ({$lagDays} days before this article was published on {$publishDateFormatted}).

You MUST follow these framing rules:
1. LEAD with TODAY'S development — this is the news hook that is actually new (e.g. an administrative order, an NHRC notice, a court hearing, a public reaction, or an official statement issued today or in the last 24 hours).
2. Reference the root event as HISTORICAL CONTEXT in the body. Use phrases like:
   - "In a follow-up to the tragic incident that occurred on {$rootDateFormatted}..."
   - "Following the [event type] that took place {$lagDays} days ago..."
   - "Weeks after the [incident], authorities today announced..."
3. Do NOT frame the article as if the original incident happened today. The incident is historical. The story today is the REACTION or RESPONSE to it.
4. The headline and lead paragraph must reflect what is NEW TODAY — not what happened on {$rootDateFormatted}.
5. In your Key Takeaways, clearly distinguish: (a) when the incident occurred, (b) what new development happened today.
GUARDRAIL;
        }

        if ($storyType === 'background') {
            return <<<GUARDRAIL
TEMPORAL FRAMING NOTE:
This article covers an event from {$rootDateFormatted} that is being reported with additional context today ({$publishDateFormatted}).
Frame the article around the most current available information. Reference the original event timing accurately — do not present it as happening "right now" or "today" if details indicate it occurred earlier.
GUARDRAIL;
        }

        return null;
    }
}
