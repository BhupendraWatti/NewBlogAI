<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\AIProviderManager\Services\AIProviderService;
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
                        'pipeline_run_id' => $run->id,
                        'position'        => $index + 1,
                        'title'           => mb_substr(trim((string) $candidate['title']), 0, 500),
                        'summary'         => $candidate['summary'] ?? null,
                        'source_references' => $candidate['source_references'] ?? [],
                        'keywords'        => $candidate['keywords'] ?? [],
                        'trend_score'     => $this->clampScore($candidate['trend_score'] ?? 0),
                        'freshness_score' => $this->clampScore($candidate['freshness_score'] ?? 0),
                        'uniqueness_hash' => NewsCandidate::hashTitle((string) $candidate['title']),
                        'metadata'        => ['event_date' => $candidate['event_date'] ?? null],
                        'status'          => NewsCandidate::STATUS_CANDIDATE,
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

        // Sort by priority order defined in PROVIDER_PRIORITY
        $priorityMap = array_flip(self::PROVIDER_PRIORITY);

        $sorted = $allEnabled->sortBy(
            fn (AIProvider $p) => $priorityMap[$p->provider_key] ?? PHP_INT_MAX
        )->values();

        // Bubble the explicitly preferred provider to the front
        if ($preferred && ! empty($preferred->api_key) && $preferred->is_enabled) {
            $sorted = $sorted
                ->reject(fn (AIProvider $p) => $p->id === $preferred->id)
                ->prepend($preferred);
        }

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

                    Log::warning('NewsDiscoveryService: Provider attempt failed.', [
                        'run_id'   => $run->id,
                        'provider' => $providerKey,
                        'attempt'  => $attempt,
                        'error'    => $errorMsg,
                    ]);

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
        $summary = collect($allErrors)
            ->map(fn ($errs, $key) => "{$key}: ".implode('; ', $errs))
            ->implode(' | ');

        throw new RuntimeException(
            'Discovery failed on all available providers. Errors — '.$summary
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
            $result = $driver->generate(
                $provider->api_key,
                $promptText,
                $provider->default_model,
                [
                    'max_tokens'  => self::DISCOVERY_MAX_TOKENS,
                    'temperature' => 0.2,
                    'timeout'     => 150,
                ]
            );

            $totalTokens['prompt']     += (int) ($result['prompt_tokens'] ?? 0);
            $totalTokens['completion'] += (int) ($result['completion_tokens'] ?? 0);
            $totalTokens['total']      += (int) ($result['total_tokens'] ?? 0);
            $totalCost                 += (float) ($result['estimated_cost'] ?? 0.0);

            $parsed   = $this->parseCandidates((string) ($result['text'] ?? ''));
            $filtered = $this->duplicates->filterUnique(array_merge($unique, $parsed), $site->id);

            $unique         = array_slice($filtered['unique'], 0, self::CANDIDATE_TARGET + 3);
            $excludedTitles = array_merge($excludedTitles, array_column($filtered['duplicates'], 'title'));

            Log::info('NewsDiscoveryService: attempt completed.', [
                'run_id'             => $run->id,
                'provider'           => $provider->provider_key,
                'attempt'            => $attempt,
                'unique_count'       => count($unique),
                'duplicates_dropped' => count($filtered['duplicates']),
            ]);
        }

        if (count($unique) < self::CANDIDATE_TARGET) {
            throw new RuntimeException(sprintf(
                'Provider "%s" produced only %d unique candidates (target %d) after %d attempts.',
                $provider->provider_key,
                count($unique),
                self::CANDIDATE_TARGET,
                self::MAX_ATTEMPTS,
            ));
        }

        return [
            array_slice($unique, 0, self::CANDIDATE_TARGET),
            $totalTokens,
            $totalCost,
        ];
    }

    /**
     * Build the discovery prompt: distinct current events, strict JSON output.
     *
     * @param array<int, string> $excludedTitles
     */
    protected function buildDiscoveryPrompt(string $category, string $language, int $count, array $excludedTitles, ?string $country = null): string
    {
        $today = now()->format('F j, Y');
        $exclusions = '';

        if (! empty($excludedTitles)) {
            $exclusions = "\nDo NOT include any event that overlaps with these already-covered headlines:\n- "
                .implode("\n- ", array_slice($excludedTitles, 0, 40));
        }

        $regionContext = $country ? " focusing specifically on national news events relevant to or occurring in {$country}" : '';

        return <<<PROMPT
You are a JSON-only news data API. Today is {$today}.

TASK: Return exactly {$count} current real-world news events from the "{$category}" category from the last 48 hours{$regionContext}.

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
    "freshness_score": 90,
    "event_date": "2024-01-15"
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

        // If strict parse fails, attempt a lenient recovery:
        // strip everything after the last '}' before ']' and retry.
        if (! is_array($decoded)) {
            $lastBrace = strrpos($jsonSlice, '}');
            if ($lastBrace !== false) {
                $recovered = substr($jsonSlice, 0, $lastBrace + 1) . ']';
                $decoded   = json_decode($recovered, true);
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
            $candidates[] = [
                'title'             => mb_substr(trim((string) $item['title']), 0, 200),
                'summary'           => isset($item['summary']) ? trim((string) $item['summary']) : null,
                'source_references' => is_array($item['source_references'] ?? null) ? $item['source_references'] : [],
                'keywords'          => is_array($item['keywords'] ?? null) ? array_values($item['keywords']) : [],
                'trend_score'       => $item['trend_score'] ?? 0,
                'freshness_score'   => $item['freshness_score'] ?? 0,
                'event_date'        => $item['event_date'] ?? null,
            ];
        }

        if (empty($candidates)) {
            throw new RuntimeException('Discovery response contained no usable candidates.');
        }

        Log::info('NewsDiscoveryService: parseCandidates succeeded.', [
            'count' => count($candidates),
        ]);

        return $candidates;
    }

    protected function clampScore(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }
}
