<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\AIProviderManager\Support\ProviderErrorClassifier;
use App\Modules\ContentPipeline\Models\NewsCandidate;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Coverage discovery stage of the newsroom workflow.
 *
 * Researches current real-world events for the pipeline's news category and
 * persists exactly CANDIDATE_TARGET unique news candidates, then stops the
 * run at status 'ready' so an employee can select ONE candidate for full
 * generation. Never generates complete articles.
 */
class NewsDiscoveryService
{
    /** The newsroom contract: exactly this many candidates per coverage run. */
    public const CANDIDATE_TARGET = 9;

    /**
     * How many candidates to request per attempt.
     * Kept equal to CANDIDATE_TARGET to keep the prompt short enough that
     * Gemini can return the full JSON in a single response without truncation.
     */
    public const OVERGENERATION_COUNT = 9;

    /** Total generation attempts (initial + one retry) before hard failure. */
    public const MAX_ATTEMPTS = 2;

    /**
     * Token budget for the discovery generate call.
     * 9 JSON objects with titles, summaries, sources etc. easily need 4-6 k tokens;
     * 8192 gives comfortable headroom while staying within Gemini free-tier limits.
     */
    private const DISCOVERY_MAX_TOKENS = 8192;

    /**
     * Attempts per provider during failover (with exponential back-off).
     * Delays: attempt 1 → 2 s, attempt 2 → 4 s, attempt 3 → 8 s.
     */
    private const FAILOVER_MAX_ATTEMPTS = 3;

    /** Base delay in seconds for exponential back-off between provider retries. */
    private const FAILOVER_BASE_DELAY_SECONDS = 2;

    /**
     * Discovery provider priority order (cheapest / fastest first).
     * Only providers that are enabled and have an API key are actually used.
     */
    private const PROVIDER_PRIORITY = ['groq', 'gemini', 'openai', 'claude', 'openrouter', 'ollama'];

    public function __construct(
        protected AIProviderService $providerService,
        protected DuplicateDetectionService $duplicates,
        protected EntitlementService $entitlements,
        protected ContentQualityFilterService $qualityFilter,
        protected GeographicDiversityEnforcer $geoEnforcer,
        protected LLMCandidateRefinementService $llmRefiner,
    ) {}

    /**
     * Execute discovery for a queued discovery run.
     *
     * When the designated discovery provider fails (429, 503, timeout) the
     * service automatically falls over to the next available provider in
     * cost order. Each provider receives up to FAILOVER_MAX_ATTEMPTS tries
     * with exponential back-off before the next one is attempted.
     *
     * @throws RuntimeException on unrecoverable failure (run is marked failed)
     */
    public function discover(PipelineRun $run): void
    {
        @set_time_limit(300); // 5 minutes execution limit for candidate discovery

        if (! $run->isDiscovery()) {
            throw new RuntimeException("Run ID {$run->id} is not a discovery run.");
        }

        $pipeline = $run->pipeline;
        $site     = $pipeline?->site;
        $prompt   = $pipeline?->prompt;

        if (! $pipeline || ! $site || ! $prompt) {
            throw new RuntimeException('Discovery run has incomplete pipeline dependencies.');
        }

        // ── Resolve the preferred provider from run properties ──────────────
        $discoveryProviderId  = $run->properties['discovery_provider_id'] ?? null;
        $preferredProvider    = $discoveryProviderId
            ? AIProvider::find($discoveryProviderId)
            : $pipeline->provider;

        // Auto-override: News discovery needs real-time search grounding to prevent hallucinations.
        // If preferred provider is not Gemini, but Gemini is enabled with an API key, we use Gemini for discovery.
        if ($preferredProvider && strtolower($preferredProvider->provider_key) !== 'gemini') {
            $geminiProvider = \App\Modules\AIProviderManager\Models\AIProvider::where('provider_key', 'gemini')
                ->where('is_enabled', true)
                ->whereNotNull('api_key')
                ->get()
                ->first(fn($p) => !empty($p->api_key));
            if ($geminiProvider) {
                Log::info("NewsDiscoveryService: Overriding preferred provider '{$preferredProvider->provider_key}' with 'gemini' to utilize search grounding.");
                $preferredProvider = $geminiProvider;
            }
        }

        Log::info('NewsDiscoveryService: Using discovery provider ID '
            .($preferredProvider?->id ?? 'none')
            ." ({$preferredProvider?->provider_key})");

        // ── Build the ordered failover list ─────────────────────────────────
        $availableProviders = $this->getAvailableProviders($preferredProvider);

        if ($availableProviders->isEmpty()) {
            throw new RuntimeException('No AI providers are enabled and configured. Please add at least one API key in AI Providers.');
        }

        $reservation = null;
        $startTime   = microtime(true);

        try {
            $run->update(['status' => 'processing', 'started_at' => now()]);

            $this->entitlements->assertCanGenerate($site);

            // Use the first available provider for the entitlement reservation
            $reservationProvider = $availableProviders->first();
            $this->entitlements->assertProviderAvailable($site, $reservationProvider->provider_key);
            $reservation = $this->entitlements->reserveGeneration(
                $site,
                $reservationProvider->provider_key,
                $reservationProvider->default_model ?? 'unknown',
                $prompt->id,
                null,
                $reservationProvider->id,
            );

            $site->loadMissing('customer');
            $country  = $pipeline->target_country ?: ($site->customer?->country ?? null);
            $category = $pipeline->news_category ?? 'global';
            $language = $pipeline->language ?: 'en';

            // ── Run discovery with automatic provider failover ───────────────
            [$unique, $totalTokens, $totalCost, $usedProviderKey] = $this->discoverWithFailover(
                $run,
                $availableProviders,
                $category,
                $language,
                $country,
            );

            // ── Persist candidates ───────────────────────────────────────────
            DB::transaction(function () use ($run, $unique) {
                foreach ($unique as $index => $candidate) {
                    NewsCandidate::create([
                        'pipeline_run_id'   => $run->id,
                        'position'          => $index + 1,
                        'title'             => mb_substr(trim((string) $candidate['title']), 0, 500),
                        'summary'           => $candidate['summary'] ?? null,
                        'source_references' => $candidate['source_references'] ?? [],
                        'keywords'          => $candidate['keywords'] ?? [],
                        'trend_score'       => $this->clampScore($candidate['trend_score'] ?? 0),
                        'freshness_score'   => $this->clampScore($candidate['freshness_score'] ?? 0),
                        'uniqueness_hash'   => NewsCandidate::hashTitle((string) $candidate['title']),
                        'metadata'          => [
                            'event_date'            => $candidate['event_date'] ?? null,
                            'published_at_relative' => $candidate['published_at_relative'] ?? null,
                        ],
                        // Persist geo and quality score fields alongside candidate data.
                        'geo_city'          => $candidate['geo_city'] ?? null,
                        'geo_state'         => $candidate['geo_state'] ?? null,
                        'quality_score'     => isset($candidate['quality_score']) ? $this->clampScore($candidate['quality_score']) : null,
                        'status'            => NewsCandidate::STATUS_CANDIDATE,
                    ]);
                }

                $run->update(['status' => PipelineRun::STATUS_READY]);
            });

            $reservation?->update([
                'prompt_tokens'     => $totalTokens['prompt'] ?: null,
                'completion_tokens' => $totalTokens['completion'] ?: null,
                'total_tokens'      => $totalTokens['total'] ?: null,
                'estimated_cost'    => $totalCost,
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status'            => 'success',
                'response_metadata' => [
                    'run_type'          => PipelineRun::TYPE_DISCOVERY,
                    'run_id'            => $run->id,
                    'provider_used'     => $usedProviderKey,
                ],
            ]);

            Log::info('NewsDiscoveryService: discovery run ready for employee selection.', [
                'run_id'          => $run->id,
                'candidates'      => self::CANDIDATE_TARGET,
                'provider_used'   => $usedProviderKey,
            ]);
        } catch (\Exception $e) {
            $reservation?->update([
                'status'            => 'failed',
                'error_log'         => $e->getMessage(),
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'response_metadata' => ['run_type' => PipelineRun::TYPE_DISCOVERY, 'run_id' => $run->id],
            ]);

            $run->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Failover helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return all enabled providers that have a valid API key, sorted so the
     * cheapest / fastest provider (Groq) is tried first.
     *
     * The preferred provider (from the run's `discovery_provider_id` or the
     * pipeline's configured provider) is always placed at position 0 so it
     * gets the first attempt.
     *
     * @return Collection<int, AIProvider>
     */
    public function getAvailableProviders(?AIProvider $preferred = null): Collection
    {
        $allEnabled = AIProvider::where('is_enabled', true)
            ->whereNotNull('api_key')
            ->get()
            ->filter(fn (AIProvider $p) => ! empty($p->api_key));

        // Cooldown check / Auto-recovery
        $healthy = $allEnabled->filter(function (AIProvider $p) {
            $p->checkRecovery();
            return $p->status === 'healthy';
        });

        // Sort by priority ascending, then preferred first
        $sorted = $healthy->sortBy(function (AIProvider $p) use ($preferred) {
            $isPreferred = ($preferred && $p->id === $preferred->id) ? 0 : 1;
            return [$p->priority, $isPreferred, $p->id];
        })->values();

        return $sorted;
    }

    /**
     * Run the discovery generation loop with automatic provider failover.
     *
     * Tries each provider in order. Each provider gets up to
     * FAILOVER_MAX_ATTEMPTS attempts with exponential back-off (2 s → 4 s → 8 s).
     * If a provider succeeds, its results are returned immediately. If it
     * exhausts all attempts, the next provider is tried.
     *
     * @param  Collection<int, AIProvider>  $providers
     * @return array{0: array, 1: array{prompt:int,completion:int,total:int}, 2: float, 3: string}
     *         [candidates, tokenTotals, totalCost, usedProviderKey]
     *
     * @throws RuntimeException when ALL providers fail
     */
    public function discoverWithFailover(
        PipelineRun $run,
        Collection $providers,
        string $category,
        string $language,
        ?string $country = null,
    ): array {
        $allErrors = [];

        foreach ($providers as $provider) {
            $providerKey = $provider->provider_key;

            for ($attempt = 1; $attempt <= self::FAILOVER_MAX_ATTEMPTS; $attempt++) {
                try {
                    Log::info('NewsDiscoveryService: Trying provider.', [
                        'run_id'       => $run->id,
                        'provider'     => $providerKey,
                        'attempt'      => $attempt,
                        'max_attempts' => self::FAILOVER_MAX_ATTEMPTS,
                    ]);

                    $result = $this->runDiscoveryWithProvider(
                        $run,
                        $provider,
                        $category,
                        $language,
                        $country,
                    );

                    Log::info('NewsDiscoveryService: Provider succeeded.', [
                        'run_id'   => $run->id,
                        'provider' => $providerKey,
                        'attempt'  => $attempt,
                    ]);

                    return array_merge($result, [$providerKey]);

                } catch (\Exception $e) {
                    $errorMsg = $e->getMessage();
                    $allErrors[$providerKey][] = "attempt {$attempt}: {$errorMsg}";

                    $provider->handleFailure($e);

                    Log::warning('NewsDiscoveryService: Provider attempt failed.', [
                        'run_id'   => $run->id,
                        'provider' => $providerKey,
                        'attempt'  => $attempt,
                        'error'    => $errorMsg,
                    ]);

                    // Permanent failures (bad key, connection refused, bad
                    // request) can never succeed on retry and only waste
                    // back-off time and tokens — fail over to the next
                    // provider immediately instead of retrying this one.
                    // Rate limits are also skipped here: the driver already
                    // applied its own back-off, and the provider won't reset
                    // within our budget, so move straight to the next one.
                    if (! ProviderErrorClassifier::shouldRetrySameProvider($e)) {
                        Log::info('NewsDiscoveryService: Not retrying this provider, moving to next.', [
                            'run_id'   => $run->id,
                            'provider' => $providerKey,
                            'reason'   => ProviderErrorClassifier::reason($e),
                        ]);
                        break;
                    }

                    // Exponential back-off between retries (not after the last attempt)
                    if ($attempt < self::FAILOVER_MAX_ATTEMPTS) {
                        $delaySeconds = self::FAILOVER_BASE_DELAY_SECONDS ** $attempt; // 2, 4, 8
                        Log::debug("NewsDiscoveryService: Backing off {$delaySeconds}s before next attempt.");
                        sleep($delaySeconds);
                    }
                }
            }

            Log::warning('NewsDiscoveryService: All attempts failed for provider, moving to next.', [
                'run_id'   => $run->id,
                'provider' => $providerKey,
                'errors'   => $allErrors[$providerKey] ?? [],
            ]);
        }

        // Every provider failed — build a descriptive exception message
        throw new RuntimeException(
            \App\Modules\ContentPipeline\Support\PipelineErrorFormatter::format($allErrors, 'Discovery')
        );
    }

    /**
     * Execute exactly one full discovery attempt using the given provider.
     *
     * Runs up to MAX_ATTEMPTS generation loops internally (each loop requests
     * OVERGENERATION_COUNT candidates and de-duplicates them). Returns the
     * collected unique candidates once CANDIDATE_TARGET is reached.
     *
     * @return array{0: array, 1: array{prompt:int,completion:int,total:int}, 2: float}
     *         [candidates, tokenTotals, totalCost]
     *
     * @throws RuntimeException if this provider cannot produce enough unique candidates
     */
    protected function runDiscoveryWithProvider(
        PipelineRun $run,
        AIProvider $provider,
        string $category,
        string $language,
        ?string $country = null,
    ): array {
        $excludedTitles = [];
        $unique         = [];
        $totalTokens    = ['prompt' => 0, 'completion' => 0, 'total' => 0];
        $totalCost      = 0.0;

        $site = $run->pipeline->site;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS && count($unique) < self::CANDIDATE_TARGET; $attempt++) {
            $needed     = self::OVERGENERATION_COUNT - count($unique);
            $promptText = $this->buildDiscoveryPrompt(
                $category,
                $language,
                $needed,
                array_merge($excludedTitles, array_column($unique, 'title')),
                $country,
            );

            $driver = $this->providerService->getDriver($provider->provider_key);

            // BUG 1 FIX: Use standard google_search tool for grounding, as
            // googleSearchRetrieval is only supported in Vertex AI / enterprise
            // accounts and throws a 400 error on Developer API keys.
            $tools = null;
            if (strtolower($provider->provider_key) === 'gemini') {
                $tools = [
                    [
                        'google_search' => (object) [],
                    ],
                ];
            }

            $result = $driver->generate(
                $provider->api_key,
                $promptText,
                $provider->default_model,
                [
                    'max_tokens'  => self::DISCOVERY_MAX_TOKENS,
                    'temperature' => 0.2,
                    'timeout'     => 150,
                    'tools'       => $tools,
                ]
            );

            // Update rate limits in database
            if (!empty($result['rate_limits'])) {
                $limits = $result['rate_limits'];
                $provider->updateRateLimits(
                    isset($limits['limit']) ? intval($limits['limit']) : null,
                    isset($limits['remaining']) ? intval($limits['remaining']) : null,
                    $limits['reset'] ?? null
                );
            }

            $totalTokens['prompt']     += (int) ($result['prompt_tokens'] ?? 0);
            $totalTokens['completion'] += (int) ($result['completion_tokens'] ?? 0);
            $totalTokens['total']      += (int) ($result['total_tokens'] ?? 0);
            $totalCost                 += (float) ($result['estimated_cost'] ?? 0.0);

            $parsed   = $this->parseCandidates((string) ($result['text'] ?? ''));

            // LLM editorial refinement gate: intercepts the raw parsed batch
            // BEFORE deduplication and DB persistence. A cheap, fast LLM deduplicates
            // same-event stories, drops gossip/trash, enforces geographic balance, and
            // fills in missing geo fields in one pass. Fail-open: on any LLM error,
            // $parsed is returned unchanged.
            $refinementResult = $this->llmRefiner->refine($parsed, $provider, $category, $country);
            $parsed           = $refinementResult['candidates'];

            // Accumulate LLM refinement token costs into the run totals
            $totalTokens['prompt']     += (int) ($refinementResult['prompt_tokens'] ?? 0);
            $totalTokens['completion'] += (int) ($refinementResult['completion_tokens'] ?? 0);
            $totalTokens['total']      += (int) ($refinementResult['total_tokens'] ?? 0);
            $totalCost                 += (float) ($refinementResult['estimated_cost'] ?? 0.0);

            $filtered = $this->duplicates->filterUnique(array_merge($unique, $parsed), $site->id);

            // Quality filter: drop candidates that fail editorial standards.
            $qualityResult  = $this->qualityFilter->filter($filtered['unique']);
            $qualityPassed  = $qualityResult['passed'];
            $qualityDropped = count($qualityResult['rejected']);

            // Geographic diversity enforcer: limit over-represented regions.
            $geoResult  = $this->geoEnforcer->filter($qualityPassed);
            $geoAllowed = $geoResult['passed'];
            $geoBlocked = count($geoResult['blocked']);

            $unique         = array_slice($geoAllowed, 0, self::CANDIDATE_TARGET + 3);
            $excludedTitles = array_merge($excludedTitles, array_column($filtered['duplicates'], 'title'));

            Log::info('NewsDiscoveryService: attempt completed.', [
                'run_id'              => $run->id,
                'provider'            => $provider->provider_key,
                'attempt'             => $attempt,
                'unique_count'        => count($unique),
                'duplicates_dropped'  => count($filtered['duplicates']),
                'llm_refined_dropped' => $refinementResult['dropped_count'] ?? 0,
                'quality_dropped'     => $qualityDropped,
                'geo_blocked'         => $geoBlocked,
            ]);
        }

        // Relax shortfall constraint: accept whatever unique candidates are generated (even 1, 2, or 3)
        // to support niche regional/filtered runs. Throw only if count is below 1.
        if (count($unique) < 1) {
            throw new RuntimeException("Could not generate enough unique candidates. Please broaden keywords.");
        }

        return [
            array_slice($unique, 0, self::CANDIDATE_TARGET),
            $totalTokens,
            $totalCost,
        ];
    }

    protected function buildDiscoveryPrompt(string $category, string $language, int $count, array $excludedTitles, ?string $country = null): string
    {
        $today       = now()->format('F j, Y');
        $todayIso    = now()->format('Y-m-d');
        $yesterdayIso = now()->subDay()->format('Y-m-d');
        $currentTime = now()->format('H:i:s');
        $exclusions  = '';

        if (! empty($excludedTitles)) {
            $exclusions = "\nDo NOT include any event that overlaps with these already-covered headlines:\n- "
                .implode("\n- ", array_slice($excludedTitles, 0, 40));
        }

        $predefined = ['global', 'trending', 'local', 'technology', 'business', 'politics', 'sports', 'health', 'science', 'entertainment'];
        $isCustomTopic = !in_array(strtolower($category), $predefined, true);

        $topicConstraint = $isCustomTopic
            ? " focusing specifically on the topic or keyword '{$category}'"
            : " from the '{$category}' category";

        // regionContext must only append the COUNTRY/REGION clause — never repeat the topic keyword,
        // because $topicConstraint already contains it for custom topics.
        $regionContext = $country
            ? ($isCustomTopic
                ? " occurring in or relevant to {$country}"
                : " focusing specifically on national news events relevant to or occurring in {$country}")
            : '';

        // Build geographic diversity instruction for the prompt
        $geoInstruction = $country
            ? "- If the region is {$country}: spread stories across different states/regions — do NOT cluster multiple stories in the same city."
            : '- Spread stories across different cities and regions globally.';

        // Embed explicit date range operators so Gemini Search grounding
        // anchors its retrieval to the last 48 hours rather than returning
        // evergreen top-ranked articles. The "after:" / "before:" syntax is
        // recognised by Google Search and biases the grounding toward fresh results.
        return <<<PROMPT
You are a JSON-only news data API. Today is {$today} ({$todayIso}), current time is {$currentTime}.

SEARCH CONTEXT — USE THIS DATE RANGE FOR ALL QUERIES:
  after:{$yesterdayIso} before:{$todayIso}
  Restrict ALL search retrievals to articles published in the last 48 hours ONLY.
  Do NOT surface articles older than {$yesterdayIso}.

TASK: Return exactly {$count} current, extremely fresh real-world news events{$topicConstraint} published after:{$yesterdayIso}{$regionContext}.
Do NOT return news older than 48 hours. Prioritize breaking news and trending events that happened within the last 30 minutes, 1 hour, 4 hours, or up to 24 hours.

GEOGRAPHIC DIVERSITY RULES (strictly enforced):
- Each story MUST come from a DIFFERENT city wherever possible. Do NOT return multiple stories from the same city.
- Spread coverage across different states/regions. Maximum 2 stories per state/region.
{$geoInstruction}
- "geo_city": the primary city where the event occurred (e.g. "Mumbai"). Use null if the story is national/international with no single city focus.
- "geo_state": the state or region of the event (e.g. "Maharashtra"). Use null if national/international.

QUALITY RULES (strictly enforced):
- Do NOT include viral social media gossip, TikTok/Reel rumours, or sensational gossip about local officials.
- Do NOT include minor domestic crimes, petty theft, or local brawls with no wider significance.
- Do NOT include unverified rumours or anonymous "sources say" gossip without a named publisher.
- Only include events that have been reported by a named, credible news outlet.

CRITICAL TEMPORAL RULES (must be strictly followed):
- ONLY events published after:{$yesterdayIso}. Reject anything older than 48 hours.
- Do NOT include events that are scheduled to happen in the future (e.g. upcoming festivals, planned events, future elections, preview articles). These are anticipated searches, NOT live news.
- The "event_date" MUST be {$todayIso} or {$yesterdayIso}. Any earlier date is a red flag of stale content — reject it.
- If a topic is trending because people are searching for an UPCOMING event (e.g. a festival weeks away), do NOT include it — it has no live news value yet.
- Only include events where something has ALREADY HAPPENED and been reported by a news outlet.
- For truly live/breaking events set freshness_score 80–99. For events from yesterday use 50–79. For events 2–3 days old use 20–49.

STRICT URL RULES (strictly enforced):
- For "source_references", you MUST output the clean, direct publisher URL (e.g. "https://www.thehindu.com/news/national/article123.ece" or "https://timesofindia.indiatimes.com/articleshow/456.cms").
- Do NOT output the long google.com/grounding-api-redirect/... URLs. Wasting output tokens on redirect URLs will truncate the response and break the parser.

STRICT OUTPUT RULES — VIOLATIONS WILL BREAK THE PARSER:
- Your ENTIRE response must be a single valid JSON array starting with [ and ending with ]
- Do NOT write any text, explanation, or commentary before or after the JSON array
- Do NOT use markdown code fences (no ```)
- Each event must be a DISTINCT real-world story — no duplicates

Write "title" and "summary" in this language code: {$language}

Return exactly this JSON structure (no extra fields, no missing fields):
[
  {
    "title": "concise headline max 120 chars",
    "summary": "2-3 sentence factual summary of the real event",
    "source_references": [{"name": "Outlet Name", "url": "https://real-url.com"}],
    "keywords": ["keyword1", "keyword2", "keyword3"],
    "trend_score": 85,
    "freshness_score": 95,
    "event_date": "{$todayIso}",
    "published_at_relative": "relative time of news event, e.g. '30 mins ago', '1 hour ago', '4 hours ago', or '12 hours ago' relative to {$currentTime}",
    "geo_city": "City name or null",
    "geo_state": "State/region name or null"
  }
]
{$exclusions}
PROMPT;
    }


    /**
     * Parse the AI response into candidate arrays.
     *
     * Handles:
     *  - Markdown code fences (```json … ```)
     *  - Gemini 2.5 "thinking" preamble text before the JSON array
     *  - Partial/truncated arrays (keeps whatever objects are fully closed)
     *
     * @return array<int, array>
     */
    protected function parseCandidates(string $text): array
    {
        $text = trim($text);

        // Strip markdown code fences (```json … ``` or ``` … ```)
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text) ?? $text;
        $text = preg_replace('/^```\s*$/m', '', $text) ?? $text;

        // Find the FIRST '[' (start of JSON array) — any thinking-model preamble
        // text appears before the array and is safely skipped this way.
        $start = strpos($text, '[');
        if ($start === false) {
            Log::error('NewsDiscoveryService: No JSON array found in discovery response.', [
                'response_preview' => mb_substr($text, 0, 500),
            ]);
            throw new RuntimeException('Discovery response did not contain a JSON array.');
        }

        // Find the LAST ']' — this handles truncated responses by closing the array
        // at whatever the last complete object boundary is.
        $end = strrpos($text, ']');
        if ($end === false || $end <= $start) {
            // Response was truncated before any closing bracket — try to recover
            // by finding the last fully-closed object and appending "]}" manually.
            $lastClose = strrpos($text, '}');
            if ($lastClose !== false && $lastClose > $start) {
                $text = substr($text, $start, $lastClose - $start + 1) . ']';
                $end   = strlen($text) - 1;
                $start = 0;
                Log::warning('NewsDiscoveryService: Truncated JSON array — recovered by closing at last "}".');
            } else {
                throw new RuntimeException('Discovery response JSON array was malformed or empty.');
            }
        }

        $jsonSlice = substr($text, $start, $end - $start + 1);
        $decoded   = json_decode($jsonSlice, true);

        // If strict parse fails, attempt a robust recovery for truncated JSON arrays:
        // Locate the last fully-closed candidate object (indented by 2 spaces: "\n  }")
        // and safely close the array there.
        if (! is_array($decoded)) {
            $lastClose = strrpos($jsonSlice, "\n  }");
            if ($lastClose === false) {
                $lastClose = strrpos($jsonSlice, "\r\n  }");
            }
            if ($lastClose !== false) {
                $recovered = substr($jsonSlice, 0, $lastClose + strlen(str_contains($jsonSlice, "\r\n") ? "\r\n  }" : "\n  }")) . "\n]";
                $decoded   = json_decode($recovered, true);
                if (is_array($decoded)) {
                    Log::warning('NewsDiscoveryService: Truncated discovery JSON recovered by closing at last complete candidate object.', [
                        'original_length'  => strlen($jsonSlice),
                        'recovered_length' => strlen($recovered),
                        'parsed_count'     => count($decoded),
                    ]);
                }
            }
        }

        if (! is_array($decoded)) {
            Log::error('NewsDiscoveryService: JSON decode failed after recovery attempts.', [
                'json_error'       => json_last_error_msg(),
                'response_preview' => mb_substr($jsonSlice, 0, 500),
            ]);
            throw new RuntimeException('Discovery response JSON could not be parsed: '.json_last_error_msg());
        }

        $candidates = [];
        foreach ($decoded as $item) {
            if (! is_array($item) || trim((string) ($item['title'] ?? '')) === '') {
                continue;
            }
            $rawFreshness = (int) ($item['freshness_score'] ?? 0);
            $sourceRefs   = is_array($item['source_references'] ?? null) ? $item['source_references'] : [];

            // Bug Fix #1: If the AI claims freshness > 80 but provides NO source reference URL,
            // cap it at 60 — we cannot verify the recency without a source, so treat as unverifiable.
            $hasVerifiableSource = collect($sourceRefs)->contains(fn ($s) => !empty($s['url']) && str_starts_with((string) $s['url'], 'http'));
            if ($rawFreshness > 80 && !$hasVerifiableSource) {
                $rawFreshness = 60;
            }

            $candidates[] = [
                'title'             => mb_substr(trim((string) $item['title']), 0, 200),
                'summary'           => isset($item['summary']) ? trim((string) $item['summary']) : null,
                'source_references' => $sourceRefs,
                'keywords'          => is_array($item['keywords'] ?? null) ? array_values($item['keywords']) : [],
                'trend_score'       => $item['trend_score'] ?? 0,
                'freshness_score'   => $rawFreshness,
                'event_date'        => $item['event_date'] ?? null,
                'published_at_relative' => $item['published_at_relative'] ?? null,
                // Geographic fields
                'geo_city'          => isset($item['geo_city']) && is_string($item['geo_city']) ? mb_substr(trim($item['geo_city']), 0, 100) : null,
                'geo_state'         => isset($item['geo_state']) && is_string($item['geo_state']) ? mb_substr(trim($item['geo_state']), 0, 100) : null,
            ];
        }

        if (empty($candidates)) {
            throw new RuntimeException('Discovery response contained no usable candidates.');
        }

        // Temporal freshness validation: penalize freshness scores for future events
        // (anticipation spikes) and for news that is older than 7 days.
        $candidates = $this->validateAndCorrectFreshness($candidates);

        Log::info('NewsDiscoveryService: parseCandidates succeeded.', [
            'count' => count($candidates),
        ]);

        return $candidates;
    }

    protected function clampScore(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    /**
     * Validate and correct freshness scores based on the event_date
     * and the published_at_relative string.
     *
     * Uses explicit Carbon lt()/gt() comparisons to determine past/future,
     * then computes a plain integer day offset.
     *
     * Cap schedule:
     *   - event_date in the future   → cap at 35  (anticipation spike, not live news)
     *   - 1–2 days old               → no cap     (genuinely fresh)
     *   - 3–7 days old               → cap at 65
     *   - 8–17 days old              → cap at 40
     *   - 18–30 days old             → cap at 25
     *   - > 30 days old              → cap at 10
     *
     * Secondary check: parse published_at_relative ("N days ago", "N hours ago")
     * and compute a second cap. The LOWER of the two caps wins.
     *
     * When BOTH event_date AND published_at_relative are missing, cap at 60 —
     * we cannot certify freshness for an undated candidate.
     *
     * @param  array<int, array>  $candidates
     * @return array<int, array>
     */
    private function validateAndCorrectFreshness(array $candidates): array
    {
        $today = now()->startOfDay();

        foreach ($candidates as &$candidate) {
            $originalScore      = (int) ($candidate['freshness_score'] ?? 0);
            $eventDateStr       = $candidate['event_date'] ?? null;
            $relativeStr        = $candidate['published_at_relative'] ?? null;
            $capFromDate        = null;
            $capFromRelative    = null;

            // ── Cap from event_date ────────────────────────────────────────────
            if (! empty($eventDateStr)) {
                try {
                    $eventDate = \Carbon\Carbon::parse((string) $eventDateStr)->startOfDay();

                    if ($eventDate->gt($today)) {
                        // FUTURE event — anticipation spike, not live news
                        $capFromDate = 35;
                        $candidate['freshness_penalty_reason'] = 'future_event';
                        Log::warning('NewsDiscoveryService: Future event detected — penalizing freshness.', [
                            'title'      => mb_substr($candidate['title'], 0, 80),
                            'event_date' => $eventDateStr,
                            'original'   => $originalScore,
                        ]);
                    } else {
                        // Past event — compute how many days old.
                        // Bug Fix #4: diffInDays(today, today) = 0, but the event
                        // could be up to 47 hours old. Use a 1-day cap (75) for
                        // same-day events to prevent inflated freshness on old stories.
                        $daysOld = (int) $today->diffInDays($eventDate); // always non-negative

                        if ($daysOld === 0) {
                            $capFromDate = 75; // same calendar day — cap, not free-pass
                        } elseif ($daysOld <= 2) {
                            $capFromDate = null; // genuinely fresh — no cap
                        } elseif ($daysOld <= 7) {
                            $capFromDate = 65;
                            $candidate['freshness_penalty_reason'] = 'recent_event';
                        } elseif ($daysOld <= 17) {
                            $capFromDate = 40;
                            $candidate['freshness_penalty_reason'] = 'stale_event';
                        } elseif ($daysOld <= 30) {
                            $capFromDate = 25;
                            $candidate['freshness_penalty_reason'] = 'stale_event';
                        } else {
                            $capFromDate = 10;
                            $candidate['freshness_penalty_reason'] = 'very_old_event';
                        }

                        if ($capFromDate !== null) {
                            Log::info('NewsDiscoveryService: Past event freshness cap applied.', [
                                'title'      => mb_substr($candidate['title'], 0, 80),
                                'event_date' => $eventDateStr,
                                'days_old'   => $daysOld,
                                'cap'        => $capFromDate,
                                'original'   => $originalScore,
                            ]);
                        }
                    }
                } catch (\Throwable) {
                    // Unparsable date — skip date-based cap
                }
            }

            // ── Cap from published_at_relative string ──────────────────────────
            // Parses: "30 mins ago", "2 hours ago", "3 days ago", "1 week ago"
            if (! empty($relativeStr)) {
                $capFromRelative = $this->capFromRelativeString((string) $relativeStr);
            }

            // ── Apply the LOWER (stricter) of the two caps ────────────────────
            $effectiveCap = null;
            if ($capFromDate !== null && $capFromRelative !== null) {
                $effectiveCap = min($capFromDate, $capFromRelative);
            } elseif ($capFromDate !== null) {
                $effectiveCap = $capFromDate;
            } elseif ($capFromRelative !== null) {
                $effectiveCap = $capFromRelative;
            }

            // ── No date metadata at all → neutral cap at 60 ──────────────────
            if ($effectiveCap === null && empty($eventDateStr) && empty($relativeStr)) {
                $effectiveCap = 60;
                $candidate['freshness_penalty_reason'] = 'undated_candidate';
            }

            if ($effectiveCap !== null) {
                $corrected = min($originalScore, $effectiveCap);
                if ($corrected < $originalScore) {
                    $candidate['freshness_score'] = $corrected;
                    Log::info('NewsDiscoveryService: Freshness score corrected.', [
                        'title'     => mb_substr($candidate['title'], 0, 80),
                        'original'  => $originalScore,
                        'corrected' => $corrected,
                        'cap'       => $effectiveCap,
                        'reason'    => $candidate['freshness_penalty_reason'] ?? 'cap_applied',
                    ]);
                }
            }
        }
        unset($candidate);

        return $candidates;
    }

    /**
     * Parse a relative time string like "30 mins ago", "2 hours ago", "3 days ago"
     * and return the maximum freshness score that candidate should receive.
     *
     * Returns null if the string cannot be parsed (no cap applied).
     */
    private function capFromRelativeString(string $relative): ?int
    {
        $relative = strtolower(trim($relative));

        // Match patterns like "30 mins ago", "2 hours ago", "3 days ago", "1 week ago"
        if (preg_match('/(\d+)\s*(min|minute|hour|hr|day|week|month)/i', $relative, $m)) {
            $value = (int) $m[1];
            $unit  = strtolower($m[2]);

            $ageInHours = match (true) {
                str_starts_with($unit, 'min') => $value / 60,
                str_starts_with($unit, 'h')   => $value,
                str_starts_with($unit, 'd')   => $value * 24,
                str_starts_with($unit, 'w')   => $value * 24 * 7,
                str_starts_with($unit, 'm')   => $value * 24 * 30, // month
                default                        => null,
            };

            if ($ageInHours === null) {
                return null;
            }

            return match (true) {
                $ageInHours <= 2   => null,        // < 2h: genuinely fresh, no cap
                $ageInHours <= 24  => null,        // < 24h: fresh enough, no cap
                $ageInHours <= 48  => 75,          // 1–2 days
                $ageInHours <= 168 => 55,          // 3–7 days
                $ageInHours <= 408 => 35,          // 8–17 days
                $ageInHours <= 720 => 20,          // 18–30 days
                default            => 10,          // > 30 days
            };
        }

        return null;
    }
}
