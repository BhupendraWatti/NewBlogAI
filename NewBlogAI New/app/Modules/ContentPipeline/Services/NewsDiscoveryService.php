<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\AIProviderManager\Support\ProviderErrorClassifier;
use App\Modules\ContentPipeline\Models\NewsCandidate;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Support\DiscoveryRunTelemetry;
use App\Modules\ContentPipeline\Support\PipelineErrorFormatter;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Carbon\Carbon;
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
    /** Absolute wall-clock budget shared by every provider and retry. */
    public const REQUEST_TIMEOUT_SECONDS = 300;

    /** The newsroom contract: exactly this many candidates per coverage run. */
    public const CANDIDATE_TARGET = 9;

    /**
     * How many candidates to request per attempt.
     * Kept equal to CANDIDATE_TARGET to keep the prompt short enough that
     * Gemini can return the full JSON in a single response without truncation.
     */
    public const OVERGENERATION_COUNT = 9;

    /** Total generation attempts before hard failure. */
    public const MAX_ATTEMPTS = 3;

    /** Keep each grounded response small enough to avoid early JSON truncation. */
    public const DISCOVERY_BATCH_SIZE = 4;

    /**
     * Token budget for the grounded discovery call. Keep this bounded so one
     * free-tier Gemini request cannot reserve most of the per-minute quota.
     */
    /**
     * Token budget for the grounded discovery call.
     * Set to 8192 to prevent mid-array JSON truncation when grounded search
     * returns rich responses with long source URLs and summaries.
     * (Raised from 4096 — that was too low and caused the 'No error' parse failures.)
     */
    public const DISCOVERY_MAX_TOKENS = 8192;

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
        @set_time_limit(self::REQUEST_TIMEOUT_SECONDS + 5);

        if (! $run->isDiscovery()) {
            throw new RuntimeException("Run ID {$run->id} is not a discovery run.");
        }

        $pipeline = $run->pipeline;
        $site = $pipeline?->site;
        $prompt = $pipeline?->prompt;

        if (! $pipeline || ! $site || ! $prompt) {
            throw new RuntimeException('Discovery run has incomplete pipeline dependencies.');
        }

        // ── Resolve the preferred provider from run properties ──────────────
        $discoveryProviderId = $run->properties['discovery_provider_id'] ?? null;
        $preferredProvider = $discoveryProviderId
            ? AIProvider::find($discoveryProviderId)
            : $pipeline->provider;

        // Auto-override: News discovery needs real-time search grounding to prevent hallucinations.
        // If preferred provider is not Gemini, but Gemini is enabled with an API key, we use Gemini for discovery.
        if ($preferredProvider && strtolower($preferredProvider->provider_key) !== 'gemini') {
            $geminiProvider = AIProvider::where('provider_key', 'gemini')
                ->where('is_enabled', true)
                ->whereNotNull('api_key')
                ->get()
                ->first(fn ($p) => ! empty($p->api_key));
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
            $message = 'No AI providers are enabled and configured. Please add at least one API key in AI Providers.';
            $properties = $run->properties ?? [];
            $properties['telemetry'] = array_merge($properties['telemetry'] ?? [], [
                'stage' => 'failed',
                'error' => $message,
                'remaining_ms' => 0,
            ]);
            $run->update([
                'status' => 'failed',
                'error_message' => $message,
                'properties' => $properties,
                'completed_at' => now(),
            ]);

            // Waiting cannot make a credential appear. The important contract
            // is that one unkeyed preference never blocks other keyed Adapters.
            throw new RuntimeException($message);
        }

        $reservation = null;
        $telemetry = null;
        $startTime = microtime(true);

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
            $telemetry = new DiscoveryRunTelemetry($run, $reservation, self::REQUEST_TIMEOUT_SECONDS);

            $site->loadMissing('customer');
            $country = $pipeline->target_country ?: ($site->customer?->country ?? null);
            $category = $pipeline->news_category ?? 'global';
            $language = $pipeline->language ?: 'en';

            // ── Run discovery with automatic provider failover ───────────────
            [$unique, $totalTokens, $totalCost, $usedProviderKey] = $this->discoverWithFailover(
                $run,
                $availableProviders,
                $category,
                $language,
                $telemetry,
                $country,
            );

            // ── Persist candidates ───────────────────────────────────────────
            DB::transaction(function () use ($run, $unique) {
                foreach ($unique as $index => $candidate) {
                    NewsCandidate::create([
                        'pipeline_run_id' => $run->id,
                        'position' => $index + 1,
                        'title' => mb_substr(trim((string) $candidate['title']), 0, 500),
                        'summary' => $candidate['summary'] ?? null,
                        'source_references' => $candidate['source_references'] ?? [],
                        'keywords' => $candidate['keywords'] ?? [],
                        'trend_score' => $this->clampScore($candidate['trend_score'] ?? 0),
                        'freshness_score' => $this->clampScore($candidate['freshness_score'] ?? 0),
                        'uniqueness_hash' => NewsCandidate::hashTitle((string) $candidate['title']),
                        'metadata' => [
                            'event_date' => $candidate['event_date'] ?? null,
                            'published_at_relative' => $candidate['published_at_relative'] ?? null,
                        ],
                        // Persist geo and quality score fields alongside candidate data.
                        'geo_city' => $candidate['geo_city'] ?? null,
                        'geo_state' => $candidate['geo_state'] ?? null,
                        'quality_score' => isset($candidate['quality_score']) ? $this->clampScore($candidate['quality_score']) : null,
                        'status' => NewsCandidate::STATUS_CANDIDATE,
                    ]);
                }

                $run->update([
                    'status' => PipelineRun::STATUS_READY,
                    'completed_at' => now(),
                ]);
            });

            $telemetry->complete($usedProviderKey);

            $reservation?->update([
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'success',
            ]);

            Log::info('NewsDiscoveryService: discovery run ready for employee selection.', [
                'run_id' => $run->id,
                'candidates' => self::CANDIDATE_TARGET,
                'provider_used' => $usedProviderKey,
            ]);
        } catch (\Exception $e) {
            $telemetry?->fail($e->getMessage());
            $reservation?->update([
                'status' => 'failed',
                'error_log' => $e->getMessage(),
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ]);

            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
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

        // Grounded Adapters are preferred for current-news accuracy, but must
        // never hide other keyed healthy Adapters from the failover list.
        $sorted = $healthy->sortBy(function (AIProvider $p) use ($preferred) {
            $isGrounded = $p->supportsGrounding() ? 0 : 1;
            $isPreferred = ($preferred && $p->id === $preferred->id) ? 0 : 1;

            return [$isGrounded, $isPreferred, $p->priority, $p->id];
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
     *                                                                                             [candidates, tokenTotals, totalCost, usedProviderKey]
     *
     * @throws RuntimeException when ALL providers fail
     */
    public function discoverWithFailover(
        PipelineRun $run,
        Collection $providers,
        string $category,
        string $language,
        DiscoveryRunTelemetry $telemetry,
        ?string $country = null,
    ): array {
        $allErrors = [];

        foreach ($providers as $provider) {
            $providerKey = $provider->provider_key;

            for ($attempt = 1; $attempt <= self::FAILOVER_MAX_ATTEMPTS; $attempt++) {
                try {
                    $telemetry->beginAttempt($provider, $attempt);
                    Log::info('NewsDiscoveryService: Trying provider.', [
                        'run_id' => $run->id,
                        'provider' => $providerKey,
                        'attempt' => $attempt,
                        'max_attempts' => self::FAILOVER_MAX_ATTEMPTS,
                    ]);

                    $result = $this->runDiscoveryWithProvider(
                        $run,
                        $provider,
                        $category,
                        $language,
                        $telemetry,
                        $country,
                    );

                    Log::info('NewsDiscoveryService: Provider succeeded.', [
                        'run_id' => $run->id,
                        'provider' => $providerKey,
                        'attempt' => $attempt,
                    ]);

                    return array_merge($result, [$providerKey]);

                } catch (\Exception $e) {
                    $errorMsg = $e->getMessage();
                    $allErrors[$providerKey][] = "attempt {$attempt}: {$errorMsg}";

                    $provider->handleFailure($e);
                    $telemetry->recordFailure($provider, $attempt, $errorMsg);

                    Log::warning('NewsDiscoveryService: Provider attempt failed.', [
                        'run_id' => $run->id,
                        'provider' => $providerKey,
                        'attempt' => $attempt,
                        'error' => $errorMsg,
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
                            'run_id' => $run->id,
                            'provider' => $providerKey,
                            'reason' => ProviderErrorClassifier::reason($e),
                        ]);
                        break;
                    }

                    // Exponential back-off between retries (not after the last attempt)
                    if ($attempt < self::FAILOVER_MAX_ATTEMPTS) {
                        $delaySeconds = self::FAILOVER_BASE_DELAY_SECONDS ** $attempt; // 2, 4, 8
                        $delaySeconds = min($delaySeconds, max(0, $telemetry->remainingSeconds() - 1));
                        if ($delaySeconds <= 0) {
                            $telemetry->assertWithinDeadline();
                            break;
                        }
                        Log::debug("NewsDiscoveryService: Backing off {$delaySeconds}s before next attempt.");
                        sleep($delaySeconds);
                    }
                }
            }

            Log::warning('NewsDiscoveryService: All attempts failed for provider, moving to next.', [
                'run_id' => $run->id,
                'provider' => $providerKey,
                'errors' => $allErrors[$providerKey] ?? [],
            ]);
        }

        // Every provider failed — build a descriptive exception message
        throw new RuntimeException(
            PipelineErrorFormatter::format($allErrors, 'Discovery')
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
     *                                                                                  [candidates, tokenTotals, totalCost]
     *
     * @throws RuntimeException if this provider cannot produce enough unique candidates
     */
    protected function runDiscoveryWithProvider(
        PipelineRun $run,
        AIProvider $provider,
        string $category,
        string $language,
        DiscoveryRunTelemetry $telemetry,
        ?string $country = null,
    ): array {
        $excludedTitles = [];
        $unique = [];
        $totalTokens = ['prompt' => 0, 'completion' => 0, 'total' => 0];
        $totalCost = 0.0;

        $site = $run->pipeline->site;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS && count($unique) < self::CANDIDATE_TARGET; $attempt++) {
            $telemetry->assertWithinDeadline();
            $needed = min(self::DISCOVERY_BATCH_SIZE, self::CANDIDATE_TARGET - count($unique));
            $promptText = $this->buildDiscoveryPrompt(
                $category,
                $language,
                $needed,
                array_merge($excludedTitles, array_column($unique, 'title')),
                $country,
            );

            $driver = $this->providerService->getDriver($provider->provider_key);

            // BUG 1 FIX: Use standard googleSearch tool for grounding, as
            // googleSearchRetrieval is only supported in Vertex AI / enterprise
            // accounts and throws a 400 error on Developer API keys.
            $tools = null;
            if (strtolower($provider->provider_key) === 'gemini') {
                $tools = [
                    [
                        'googleSearch' => (object) [],
                    ],
                ];
            }

            $result = $driver->generate(
                $provider->api_key,
                $promptText,
                $provider->default_model,
                [
                    'max_tokens' => self::DISCOVERY_MAX_TOKENS,
                    'temperature' => 0.2,
                    'timeout' => $telemetry->remainingSeconds(),
                    // The execution Module owns retries so nested Adapter
                    // backoffs cannot overrun the single run deadline.
                    'max_retries' => 0,
                    'tools' => $tools,
                    // Gemini 2.5 Flash defaults to dynamic thinking. For a
                    // grounded JSON extraction call, hidden reasoning can
                    // consume the output budget and leave a partial array.
                    'thinking_budget' => strtolower($provider->provider_key) === 'gemini' ? 0 : null,
                    // NOTE: json_mode (responseMimeType: application/json) MUST NOT be set
                    // when googleSearch grounding tools are active. Gemini returns HTTP 400
                    // INVALID_ARGUMENT: "Tool use with a response mime type: 'application/json'
                    // is unsupported". parseCandidates() extracts JSON from free-text safely.
                ]
            );

            // Persist provider-reported usage immediately. This retains paid
            // calls even if parsing or a later provider attempt fails.
            $telemetry->recordResponse(
                $provider->provider_key,
                $provider->default_model,
                $result,
            );

            // Update rate limits in database
            if (! empty($result['rate_limits'])) {
                $limits = $result['rate_limits'];
                $provider->updateRateLimits(
                    isset($limits['limit']) ? intval($limits['limit']) : null,
                    isset($limits['remaining']) ? intval($limits['remaining']) : null,
                    $limits['reset'] ?? null
                );
            }

            $totalTokens['prompt'] += (int) ($result['prompt_tokens'] ?? 0);
            $totalTokens['completion'] += (int) ($result['completion_tokens'] ?? 0);
            $totalTokens['total'] += (int) ($result['total_tokens'] ?? 0);
            $totalCost += (float) ($result['estimated_cost'] ?? 0.0);

            try {
                $parsed = $this->parseCandidates((string) ($result['text'] ?? ''));
            } catch (RuntimeException $e) {
                Log::warning('NewsDiscoveryService: discovery JSON parse failed; retrying with a fresh smaller batch if possible.', [
                    'run_id' => $run->id,
                    'provider' => $provider->provider_key,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'response_finish_reason' => $result['raw_response']['candidates'][0]['finishReason'] ?? null,
                    'response_preview' => mb_substr((string) ($result['text'] ?? ''), 0, 500),
                ]);

                if (count($unique) >= 1) {
                    break;
                }

                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }

                continue;
            }

            // LLM editorial refinement gate: intercepts the raw parsed batch
            // BEFORE deduplication and DB persistence. A cheap, fast LLM deduplicates
            // same-event stories, drops gossip/trash, enforces geographic balance, and
            // fills in missing geo fields in one pass. Fail-open: on any LLM error,
            // $parsed is returned unchanged.
            $refinementResult = $this->llmRefiner->refine($parsed, $provider, $category, $country);
            $parsed = $refinementResult['candidates'];

            if (($refinementResult['provider'] ?? null) !== null) {
                $telemetry->recordResponse(
                    $refinementResult['provider'],
                    $refinementResult['model'] ?? null,
                    $refinementResult,
                    'refinement_response',
                );
            }

            // Accumulate LLM refinement token costs into the run totals
            $totalTokens['prompt'] += (int) ($refinementResult['prompt_tokens'] ?? 0);
            $totalTokens['completion'] += (int) ($refinementResult['completion_tokens'] ?? 0);
            $totalTokens['total'] += (int) ($refinementResult['total_tokens'] ?? 0);
            $totalCost += (float) ($refinementResult['estimated_cost'] ?? 0.0);

            $filtered = $this->duplicates->filterUnique(array_merge($unique, $parsed), $site->id);

            // Quality filter: drop candidates that fail editorial standards.
            $qualityResult = $this->qualityFilter->filter($filtered['unique']);
            $qualityPassed = $qualityResult['passed'];
            $qualityDropped = count($qualityResult['rejected']);

            // Geographic diversity enforcer: limit over-represented regions.
            $geoResult = $this->geoEnforcer->filter($qualityPassed, $category);
            $geoAllowed = $geoResult['passed'];
            $geoBlocked = count($geoResult['blocked']);

            $unique = array_slice($geoAllowed, 0, self::CANDIDATE_TARGET + 3);
            $excludedTitles = array_merge($excludedTitles, array_column($filtered['duplicates'], 'title'));

            Log::info('NewsDiscoveryService: attempt completed.', [
                'run_id' => $run->id,
                'provider' => $provider->provider_key,
                'attempt' => $attempt,
                'unique_count' => count($unique),
                'duplicates_dropped' => count($filtered['duplicates']),
                'llm_refined_dropped' => $refinementResult['dropped_count'] ?? 0,
                'quality_dropped' => $qualityDropped,
                'geo_blocked' => $geoBlocked,
            ]);
        }

        // Relax shortfall constraint: accept runs with fewer than the target 9 candidates
        // to support niche regional/filtered topics. Throw only if fewer than 4 unique
        // candidates were collected — below this the Newsroom UI renders poorly.
        // (Documented minimum: 4. See current_issues.md Issue 5.)
        if (count($unique) < 4) {
            throw new RuntimeException('Could not generate enough unique candidates (minimum 4 required). Please broaden keywords or topic.');
        }

        return [
            array_slice($unique, 0, self::CANDIDATE_TARGET),
            $telemetry->tokens(),
            $telemetry->estimatedCostUsd(),
        ];
    }

    protected function buildDiscoveryPrompt(string $category, string $language, int $count, array $excludedTitles, ?string $country = null): string
    {
        $today = now()->format('F j, Y');
        $todayIso = now()->format('Y-m-d');
        $yesterdayIso = now()->subDay()->format('Y-m-d');
        $currentTime = now()->format('H:i:s');
        $exclusions = '';

        if (! empty($excludedTitles)) {
            $exclusions = "\nDo NOT include any event that overlaps with these already-covered headlines:\n- "
                .implode("\n- ", array_slice($excludedTitles, 0, 40));
        }

        $predefined = ['global', 'trending', 'local', 'technology', 'business', 'politics', 'sports', 'health', 'science', 'entertainment'];
        $isCustomTopic = ! in_array(strtolower($category), $predefined, true);

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

        // Custom topics can be a city/state name. Requiring different cities
        // for a query such as "Ujjain" contradicts the topic constraint and
        // guarantees a downstream geo-quota shortfall.
        $geoInstruction = $isCustomTopic
            ? "- If '{$category}' names a city or state, multiple stories MAY share that location. Diversify by event and subject instead of inventing other locations."
            : ($country
                ? "- If the region is {$country}: spread stories across different states/regions — do NOT cluster multiple stories in the same city."
                : '- Spread stories across different cities and regions globally.');
        $geoDiversityRules = $isCustomTopic
            ? '- Keep every story directly relevant to the requested topic. For a place-specific topic, same-city stories are valid when they cover distinct events.'
            : "- Each story MUST come from a DIFFERENT city wherever possible. Do NOT return multiple stories from the same city.\n- Spread coverage across different states/regions. Maximum 2 stories per state/region.";

        // Embed explicit date range operators so Gemini Search grounding
        // anchors its retrieval to the last 48 hours rather than returning
        // evergreen top-ranked articles. The "after:" / "before:" syntax is
        // recognised by Google Search and biases the grounding toward fresh results.
        $officialDesignationGuidance = $this->shouldVerifyOfficialDesignations($category)
            ? "\nOFFICIAL DESIGNATION CHECK: Use search grounding to verify current titles for any political leaders, ministers, government departments, courts, police, or public officials mentioned in selected stories. Do not rely on stored model knowledge for office holders.\n"
            : '';

        // NOTE: All line endings in this heredoc are deliberately normalized to
        // LF (\n). Mixed CRLF/LF in grounding prompts can cause Google Search
        // to truncate the after:/before: date-range operators mid-line, causing
        // the grounding engine to ignore the 48-hour temporal filter and return
        // stale/evergreen articles.
        return <<<PROMPT
You are a JSON-only news data API. Today is {$today} ({$todayIso}), current time is {$currentTime}.
{$officialDesignationGuidance}

SEARCH CONTEXT — USE THIS DATE RANGE FOR ALL QUERIES:
  after:{$yesterdayIso} before:{$todayIso}
  Restrict ALL search retrievals to articles published in the last 48 hours ONLY.
  Do NOT surface articles older than {$yesterdayIso}.

TASK: Return exactly {$count} current, fresh real-world news events{$topicConstraint} published after:{$yesterdayIso}{$regionContext}.
Do NOT return news older than 48 hours. Prioritize breaking news and trending events that happened within the last 30 minutes, 1 hour, 4 hours, or up to 24 hours.

GEOGRAPHIC DIVERSITY RULES (strictly enforced):
{$geoDiversityRules}
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
- For truly live/breaking events set freshness_score 80-99. For events from yesterday use 50-79. For events 2-3 days old use 20-49.

STRICT URL RULES (strictly enforced):
- Include two independent credible publisher sources when available; use one only when no second report exists.
- For "source_references", you MUST output the clean, direct publisher URL (e.g. "https://www.thehindu.com/news/national/article123.ece" or "https://timesofindia.indiatimes.com/articleshow/456.cms").
- Do NOT output the long google.com/grounding-api-redirect/... URLs. Wasting output tokens on redirect URLs will truncate the response and break the parser.
- Do NOT use Facebook, Instagram, TikTok, X/Twitter, or other social-media post URLs as news sources.
- Each source URL must be a direct article URL under 240 characters with tracking query parameters removed.

STRICT OUTPUT RULES — VIOLATIONS WILL BREAK THE PARSER:
- Your ENTIRE response must be a single valid JSON array starting with [ and ending with ]
- Do NOT wrap the array in any object like {"candidates":[...]} or {"results":[...]}
- Do NOT write any text, explanation, or commentary before or after the JSON array
- Do NOT use markdown code fences (no ```)
- Each event must be a DISTINCT real-world story — no duplicates

Write "title" and "summary" in this language code: {$language}

Return exactly this JSON structure (no extra fields, no missing fields):
[
  {
    "title": "concise headline max 120 chars",
    "summary": "2 to 4 concise factual sentences (300-700 characters total). Preserve every verified detail available about the precise location/landmark, occurrence time, identified people and ages, hospital status, official statements, investigation or traffic status, and public helplines. Omit details not reported by sources; never invent them.",
    "source_references": [{"name": "Outlet Name", "url": "https://real-url.com"}, {"name": "Second Outlet When Available", "url": "https://second-real-url.com"}],
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
     *  - Citation markers [1],[2] in preamble only (not inside JSON body)
     *  - Partial/truncated arrays (keeps whatever objects are fully closed)
     *
     * @return array<int, array>
     */
    protected function parseCandidates(string $text): array
    {
        $original = $text;
        $text = trim($text);

        // ── Step 1: Strip markdown code fences ──────────────────────────────
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text) ?? $text;
        $text = preg_replace('/^```\s*$/m', '', $text) ?? $text;
        $text = trim($text);

        // ── Step 2: Find the outermost JSON array boundaries ─────────────────
        // Use the FIRST '[' that is followed by whitespace and '{' (array of objects).
        // This skips any preamble text Gemini may prepend before the JSON array.
        $start = null;
        if (preg_match('/\[\s*\{/s', $text, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
        } else {
            // Fallback: first '[' in the response
            $pos = strpos($text, '[');
            if ($pos !== false) {
                $start = $pos;
            }
        }

        if ($start === null) {
            Log::error('NewsDiscoveryService: No JSON array found in discovery response.', [
                'response_preview' => mb_substr($text, 0, 800),
            ]);
            throw new RuntimeException('Discovery response did not contain a JSON array.');
        }

        // ── Step 3: Strip Gemini grounding citation markers ONLY from preamble
        // (the text BEFORE the JSON array start). Never touch the JSON body itself —
        // the old approach of stripping [1],[2] globally corrupted source_references
        // array brackets like [{"name":"BBC",...}].
        if ($start > 0) {
            $preamble = substr($text, 0, $start);
            $preamble = preg_replace('/\[\d+\]/', '', $preamble) ?? $preamble;
            $text = $preamble.substr($text, $start);
            // Recalculate start since preamble length may have changed
            if (preg_match('/\[\s*\{/s', $text, $m, PREG_OFFSET_CAPTURE)) {
                $start = $m[0][1];
            }
        }

        // ── Step 4: Find the last ']' for the array close ───────────────────
        $end = strrpos($text, ']');
        if ($end === false || $end <= $start) {
            // Truncated response — close array at the last complete object
            $lastClose = strrpos($text, '}');
            if ($lastClose !== false && $lastClose > $start) {
                $text = substr($text, $start, $lastClose - $start + 1).']';
                $end = strlen($text) - 1;
                $start = 0;
                Log::warning('NewsDiscoveryService: Truncated JSON array — recovered by closing at last "}".');
            } else {
                Log::error('NewsDiscoveryService: JSON array malformed, no closing bracket or object found.', [
                    'response_preview' => mb_substr($text, 0, 800),
                ]);
                throw new RuntimeException('Discovery response JSON array was malformed or empty.');
            }
        }

        $jsonSlice = substr($text, $start, $end - $start + 1);

        // ── Step 5: First-pass strict decode ────────────────────────────────
        $decoded = json_decode($jsonSlice, true);
        $strictJsonError = json_last_error();
        $strictJsonErrorMessage = json_last_error_msg();

        // ── Step 5b: Unwrap JSON object wrappers ─────────────────────────────
        // Gemini occasionally returns the candidate array wrapped in an object:
        //   {"candidates": [...]} or {"results": [...]} or {"data": [...]}
        // json_decode() succeeds (no error), but returns a PHP associative array
        // (not a list), causing !is_array($decoded) to pass and the error message
        // to be the misleading 'Discovery response JSON could not be parsed: No error'.
        // We detect this case and unwrap the inner array before any recovery attempt.
        if (is_array($decoded) && ! array_is_list($decoded)) {
            $wrapperKeys = ['candidates', 'results', 'data', 'items', 'news', 'articles', 'events'];
            $unwrapped = null;
            foreach ($wrapperKeys as $key) {
                if (isset($decoded[$key]) && is_array($decoded[$key]) && array_is_list($decoded[$key])) {
                    $unwrapped = $decoded[$key];
                    Log::info('NewsDiscoveryService: Unwrapped JSON object wrapper.', [
                        'wrapper_key' => $key,
                        'inner_count' => count($unwrapped),
                    ]);
                    break;
                }
            }
            // If no recognised wrapper key, fall through to recovery below
            if ($unwrapped !== null) {
                $decoded = $unwrapped;
            } else {
                // Force recovery by setting decoded to null
                $decoded = null;
            }
        }

        // ── Step 6: Recovery for truncated arrays ────────────────────────────
        // If decode fails or returns a non-list, truncate at the last fully-closed
        // object ("}") and close the array bracket to salvage partial responses.
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            $lastClose = strrpos($jsonSlice, "\n  }");
            if ($lastClose === false) {
                $lastClose = strrpos($jsonSlice, "\r\n  }");
            }
            if ($lastClose === false) {
                $lastClose = strrpos($jsonSlice, '}');
            }
            if ($lastClose !== false) {
                $recovered = substr($jsonSlice, 0, $lastClose + 1)."\n]";
                $recoveredDecoded = json_decode($recovered, true);
                if (is_array($recoveredDecoded) && array_is_list($recoveredDecoded)) {
                    $decoded = $recoveredDecoded;
                    Log::warning('NewsDiscoveryService: Truncated discovery JSON recovered at last complete object.', [
                        'original_length' => strlen($jsonSlice),
                        'recovered_length' => strlen($recovered),
                        'parsed_count' => count($decoded),
                    ]);
                    $jsonSlice = $recovered;
                }
            }
        }

        // ── Step 7: Final decode failure — log full detail and throw ─────────
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            Log::error('NewsDiscoveryService: JSON decode failed after all recovery attempts.', [
                'json_error' => $strictJsonErrorMessage,
                'json_error_code' => $strictJsonError,
                'is_array' => is_array($decoded),
                'is_list' => is_array($decoded) && array_is_list($decoded),
                'slice_length' => strlen($jsonSlice),
                'response_preview' => mb_substr($jsonSlice, 0, 800),
            ]);
            $parseDetail = ($strictJsonError === JSON_ERROR_NONE && is_array($decoded))
                ? 'Response was a JSON object, not an array — unknown wrapper structure.'
                : 'JSON parse error: '.$strictJsonErrorMessage;
            throw new RuntimeException('Discovery response JSON could not be parsed: '.$parseDetail);
        }

        $candidates = [];
        foreach ($decoded as $index => $item) {
            if (! is_array($item) || trim((string) ($item['title'] ?? '')) === '') {
                continue;
            }
            $rawFreshness = (int) ($item['freshness_score'] ?? 0);
            $sourceRefs = is_array($item['source_references'] ?? null) ? $item['source_references'] : [];
            $sourceCount = count($sourceRefs);
            $keywordCount = is_array($item['keywords'] ?? null) ? count($item['keywords']) : 0;

            // Mathematical Trending Score: coverage volume (sources) + keyword velocity
            // Introduce a deterministic position-based variance offset (unique per card)
            // so scores are dynamically scattered (e.g. 84%, 77%, 91%) and never locked/clustered.
            $baseVelocity = 45 + ($keywordCount * 6);
            $coverageBonus = $sourceCount * 18;
            $positionVariance = ($index * 7) % 19;
            $dynamicTrend = (int) min(98, max(45, $baseVelocity + $coverageBonus - $positionVariance));

            // Bug Fix #1: If the AI claims freshness > 80 but provides NO source reference URL,
            // cap it at 60 — we cannot verify the recency without a source, so treat as unverifiable.
            $hasVerifiableSource = collect($sourceRefs)->contains(fn ($s) => ! empty($s['url']) && str_starts_with((string) $s['url'], 'http'));
            if ($rawFreshness > 80 && ! $hasVerifiableSource) {
                $rawFreshness = 60;
            }

            $candidates[] = [
                'title' => mb_substr(trim((string) $item['title']), 0, 200),
                'summary' => isset($item['summary']) ? trim((string) $item['summary']) : null,
                'source_references' => $sourceRefs,
                'keywords' => is_array($item['keywords'] ?? null) ? array_values($item['keywords']) : [],
                'trend_score' => $dynamicTrend,
                'freshness_score' => $rawFreshness,
                'event_date' => $item['event_date'] ?? null,
                'published_at_relative' => $item['published_at_relative'] ?? null,
                // Geographic fields
                'geo_city' => isset($item['geo_city']) && is_string($item['geo_city']) ? mb_substr(trim($item['geo_city']), 0, 100) : null,
                'geo_state' => isset($item['geo_state']) && is_string($item['geo_state']) ? mb_substr(trim($item['geo_state']), 0, 100) : null,
            ];
        }

        if (empty($candidates)) {
            throw new RuntimeException('Discovery response contained no usable candidates.');
        }

        // Correct claimed scores. Eligibility is enforced by the editorial
        // quality gate below so all rejection policy has one locality.
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

    private function shouldVerifyOfficialDesignations(string $category): bool
    {
        $category = strtolower($category);

        return str_contains($category, 'politic')
            || str_contains($category, 'government')
            || str_contains($category, 'election')
            || str_contains($category, 'court')
            || str_contains($category, 'crime');
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
            $originalScore = (int) ($candidate['freshness_score'] ?? 0);
            $eventDateStr = $candidate['event_date'] ?? null;
            $relativeStr = $candidate['published_at_relative'] ?? null;
            $capFromDate = null;
            $capFromRelative = null;

            // ── Cap from event_date ────────────────────────────────────────────
            if (! empty($eventDateStr)) {
                try {
                    $eventDate = Carbon::parse((string) $eventDateStr)->startOfDay();

                    if ($eventDate->gt($today)) {
                        // FUTURE event — anticipation spike, not live news
                        $capFromDate = 35;
                        $candidate['freshness_penalty_reason'] = 'future_event';
                        Log::warning('NewsDiscoveryService: Future event detected — penalizing freshness.', [
                            'title' => mb_substr($candidate['title'], 0, 80),
                            'event_date' => $eventDateStr,
                            'original' => $originalScore,
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
                                'title' => mb_substr($candidate['title'], 0, 80),
                                'event_date' => $eventDateStr,
                                'days_old' => $daysOld,
                                'cap' => $capFromDate,
                                'original' => $originalScore,
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
                        'title' => mb_substr($candidate['title'], 0, 80),
                        'original' => $originalScore,
                        'corrected' => $corrected,
                        'cap' => $effectiveCap,
                        'reason' => $candidate['freshness_penalty_reason'] ?? 'cap_applied',
                    ]);
                }
            }
        }
        unset($candidate);

        return $candidates;
    }

    /**
     * Parse a relative time string like "30 mins ago", "2 hours ago", "3 days ago"
     * and return the maximum freshness score that candidate should receive using
     * the exponential decay function: score = 100 * e^(-0.04 * t).
     */
    private function capFromRelativeString(string $relative): ?int
    {
        $ageInHours = $this->relativeAgeInHours($relative);
        if ($ageInHours !== null) {

            // Stories under 24 hours old: no freshness cap — they are live/fresh news.
            // The AI's own freshness_score is trusted in this window (Bug Fix #1 above
            // already rejected scores > 80 without a source URL).
            if ($ageInHours <= 24) {
                return null;
            }

            // For content older than 24 hours: exponential decay formula S = 100 * e^(-0.04 * t)
            // Decay curve at key milestones:
            //   24h  → 38   |   48h  → 15   |   72h  → 6 (clamped to 10)
            $decayRate = 0.04;
            $decayedScore = 100 * exp(-$decayRate * $ageInHours);

            return (int) max(10, min(100, round($decayedScore)));
        }

        return null;
    }

    private function relativeAgeInHours(string $relative): ?float
    {
        if (! preg_match('/(\d+)\s*(min|minute|hour|hr|day|week|month)/i', strtolower(trim($relative)), $matches)) {
            return null;
        }

        $value = (int) $matches[1];
        $unit = strtolower($matches[2]);

        return match (true) {
            str_starts_with($unit, 'min') => $value / 60,
            str_starts_with($unit, 'h') => (float) $value,
            str_starts_with($unit, 'd') => (float) ($value * 24),
            str_starts_with($unit, 'w') => (float) ($value * 24 * 7),
            str_starts_with($unit, 'm') => (float) ($value * 24 * 30),
            default => null,
        };
    }
}
