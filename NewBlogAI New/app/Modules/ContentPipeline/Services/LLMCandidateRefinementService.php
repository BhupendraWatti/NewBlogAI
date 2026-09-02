<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\AIProviderManager\Services\AIProviderService;
use Illuminate\Support\Facades\Log;

/**
 * Issue #2 - LLM-Powered Candidate Refinement Gate.
 *
 * Intercepts the raw candidate array returned by parseCandidates() BEFORE
 * it is deduplicated or persisted. Sends the full batch to a cheap, fast
 * LLM (gemini-2.5-flash / gpt-4o-mini) acting as a senior editorial AI
 * that performs four editorial operations in a single LLM pass:
 *
 *  1. DEDUPLICATION  - merges stories covering the exact same real-world
 *     event from different publishers into one canonical entry (keeping
 *     the best title/summary and combining all source_references).
 *
 *  2. QUALITY FILTER - drops gossip, TikTok/Reel rumours, sensational
 *     local stories, unverified "sources say" claims without named sources,
 *     and minor domestic crimes with no wider editorial significance.
 *
 *  3. GEOGRAPHIC BALANCE - drops excess stories from the same city/state so
 *     the batch is geographically diverse (max 2 per city, max 4 per state).
 *     Also fills in missing geo_city / geo_state fields where inferable.
 *
 *  4. NORMALISATION  - standardises titles, trims whitespace, and ensures
 *     every surviving candidate carries the complete required schema fields.
 *
 * Fail-open design: if the LLM call fails for any reason (network error,
 * bad JSON, rate-limit exhausted), the original raw candidates are returned
 * unchanged so the discovery pipeline never hard-fails on this step.
 *
 * Token accounting: returns prompt_tokens, completion_tokens, total_tokens,
 * and estimated_cost so the caller can merge them into run-level cost tracking
 * exactly like any other generation call in this pipeline.
 *
 * Architecture contract:
 *   Input  - array<int, array{title, summary, keywords, source_references,
 *                              trend_score, freshness_score, event_date,
 *                              published_at_relative, geo_city, geo_state}>
 *   Output - array{
 *     candidates:        array<int, array>,  // cleaned, refined batch
 *     dropped_count:     int,
 *     drop_reasons:      array<int, string>,
 *     prompt_tokens:     int,
 *     completion_tokens: int,
 *     total_tokens:      int,
 *     estimated_cost:    float,
 *   }
 */
class LLMCandidateRefinementService
{
    /**
     * Token budget for the refinement call.
     * ~9 candidates x ~200 tokens each = ~1 800 input tokens.
     * 2 048 output tokens is enough for the keep/drop map without doubling
     * discovery's free-tier token pressure.
     */
    private const REFINEMENT_MAX_TOKENS = 2048;

    /**
     * Provider preference order for refinement (cheap + fast models first).
     * Only providers that are enabled and have an API key are actually used.
     */
    private const PREFERRED_PROVIDERS = ['groq', 'openai', 'claude', 'openrouter'];

    /**
     * Preferred model per provider for the refinement task.
     * Falls back to the provider's configured default_model when null.
     */
    private const PROVIDER_MODELS = [
        'groq' => 'llama-3.1-8b-instant',
        'openai' => 'gpt-4o-mini',
        'claude' => 'claude-haiku-4-5',
        'openrouter' => null,
    ];

    /**
     * JSON Schema for the structured output response.
     *
     * Passed as json_schema to generate() so every driver enforces the shape
     * natively: Gemini uses responseSchema, OpenAI/Groq/OpenRouter use
     * response_format: json_schema, and Claude uses tool-use input_schema.
     *
     * "strict": true tells OpenAI to reject any response that deviates from
     * the schema (no extra properties, no missing required fields).
     *
     * The schema describes the top-level object:
     *   { "results": [ { _idx, keep, drop_reason, title, summary,
     *                    geo_city, geo_state, source_references } ] }
     */
    private const REFINEMENT_SCHEMA = [
        'name' => 'candidate_refinement',
        'strict' => true,
        'schema' => [
            'type' => 'object',
            'required' => ['results'],
            'additionalProperties' => false,
            'properties' => [
                'results' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['_idx', 'keep'],
                        'additionalProperties' => false,
                        'properties' => [
                            '_idx' => ['type' => 'integer'],
                            'keep' => ['type' => 'boolean'],
                            'drop_reason' => ['type' => ['string', 'null']],
                            'title' => ['type' => ['string', 'null']],
                            'summary' => ['type' => ['string', 'null']],
                            'geo_city' => ['type' => ['string', 'null']],
                            'geo_state' => ['type' => ['string', 'null']],
                            'source_references' => [
                                'type' => ['array', 'null'],
                                'items' => ['type' => 'object'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    public function __construct(
        protected AIProviderService $providerService,
    ) {}

    /**
     * Run the LLM refinement pass on a raw candidate batch.
     *
     * On any LLM failure the method returns the original candidates with
     * zero token cost so the discovery pipeline never fails on this step.
     *
     * @param  array<int, array>  $rawCandidates
     * @param  AIProvider  $preferredProvider  Already-resolved provider from the caller
     * @param  string  $category  e.g. "local", "politics", "sports"
     * @param  string|null  $country  e.g. "India" — used for geo-diversity rules
     * @return array{candidates: array, dropped_count: int, drop_reasons: array,
     *               prompt_tokens: int, completion_tokens: int,
     *               total_tokens: int, estimated_cost: float}
     */
    public function refine(
        array $rawCandidates,
        AIProvider $preferredProvider,
        string $category,
        ?string $country = null,
        ?string $state = null,
    ): array {
        $passthrough = [
            'candidates' => $rawCandidates,
            'dropped_count' => 0,
            'drop_reasons' => [],
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost' => 0.0,
            'provider' => null,
            'model' => null,
        ];

        if (empty($rawCandidates)) {
            return $passthrough;
        }

        try {
            [$provider, $model] = $this->resolveProvider($preferredProvider);

            if ($provider === null) {
                Log::warning('LLMCandidateRefinementService: no usable provider found, skipping refinement.');

                return $passthrough;
            }

            $prompt = $this->buildRefinementPrompt($rawCandidates, $category, $country, $state);
            $driver = $this->providerService->getDriver($provider->provider_key);

            $result = $driver->generate(
                $provider->api_key,
                $prompt,
                $model,
                [
                    'max_tokens' => self::REFINEMENT_MAX_TOKENS,
                    'temperature' => 0.1,   // near-deterministic for editorial judgment
                    'timeout' => 90,
                    'task' => 'candidate_refinement',
                    // Enforce structured output natively in every driver:
                    // Gemini → responseMimeType + responseSchema
                    // OpenAI / Groq / OpenRouter → response_format
                    // Claude → tool-use with input_schema
                    'json_mode' => true,
                    'json_schema' => self::REFINEMENT_SCHEMA,
                ]
            );

            Log::info('LLMCandidateRefinementService: LLM call completed.', [
                'provider' => $provider->provider_key,
                'model' => $model,
                'prompt_tokens' => $result['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['completion_tokens'] ?? 0,
                'estimated_cost' => $result['estimated_cost'] ?? 0.0,
                'raw_candidates' => count($rawCandidates),
            ]);

            $refined = $this->parseRefinedCandidates((string) ($result['text'] ?? ''), $rawCandidates);

            $droppedCount = max(0, count($rawCandidates) - count($refined['candidates']));

            return [
                'candidates' => $refined['candidates'],
                'dropped_count' => $droppedCount,
                'drop_reasons' => $refined['drop_reasons'],
                'prompt_tokens' => (int) ($result['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($result['completion_tokens'] ?? 0),
                'total_tokens' => (int) ($result['total_tokens'] ?? 0),
                'estimated_cost' => (float) ($result['estimated_cost'] ?? 0.0),
                'provider' => $provider->provider_key,
                'model' => $model,
            ];

        } catch (\Throwable $e) {
            // Fail open: never block the discovery pipeline on a refinement error
            Log::warning('LLMCandidateRefinementService: refinement failed, returning raw candidates unchanged.', [
                'error' => $e->getMessage(),
            ]);

            return $passthrough;
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve which provider + model to use for the refinement call.
     *
     * Tries cheap/fast non-grounded providers first. Discovery already spent
     * the grounded Gemini call, so refinement deliberately avoids reusing
     * Gemini and fails open when no cheap refinement provider is configured.
     *
     * @return array{0: AIProvider|null, 1: string|null}
     */
    private function resolveProvider(AIProvider $preferredProvider): array
    {
        foreach (self::PREFERRED_PROVIDERS as $key) {
            $provider = AIProvider::where('provider_key', $key)
                ->where('is_enabled', true)
                ->whereNotNull('api_key')
                ->get()
                ->first(fn ($p) => ! empty($p->api_key));

            if ($provider) {
                // Skip if currently rate-limited (reset window still in the future)
                if ($provider->reset_at && $provider->reset_at->isFuture()) {
                    continue;
                }
                $model = self::PROVIDER_MODELS[$key] ?? $provider->default_model;

                return [$provider, $model];
            }
        }

        if (
            strtolower($preferredProvider->provider_key) !== 'gemini'
            && $preferredProvider->is_enabled
            && ! empty($preferredProvider->api_key)
        ) {
            return [$preferredProvider, $preferredProvider->default_model];
        }

        return [null, null];
    }

    /**
     * Build the editorial refinement prompt.
     *
     * Strict JSON-in / JSON-out instruction. The LLM receives the full raw
     * batch indexed by _idx and must return a "results" array where every
     * input item appears with keep: true/false and a drop_reason string.
     */
    private function buildRefinementPrompt(
        array $candidates,
        string $category,
        ?string $country,
        ?string $state = null
    ): string {
        $today = now()->format('F j, Y');
        $location = $state && $country ? "{$state}, {$country}" : ($state ?: $country);
        $regionContext = $location ? " Coverage region: {$location}." : '';

        // Dynamic state balance rule: avoid hard-dropping state candidates when focused on that state
        $stateBalanceRule = $state
            ? "Stories are specifically targeted to {$state}. Diversify across different cities and districts within {$state}."
            : "Apply the same logic for geo_state with a maximum of 4.";

        // Tag each candidate with its index so we can map results back
        $indexed = array_map(
            fn ($c, $i) => array_merge(['_idx' => $i], $c),
            $candidates,
            array_keys($candidates)
        );

        $candidateJson = json_encode($indexed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are a senior editorial AI for a professional news publishing platform. Today is {$today}.{$regionContext}

You have received a batch of raw news candidates in the "{$category}" category. Your job is to clean this batch by applying the four editorial rules below, then return ONLY a valid JSON object — no markdown, no code fences, no commentary.

RULE 1 - DEDUPLICATE
If two or more candidates describe the SAME real-world event (same incident, announcement, match result, or story) — even if worded differently or sourced from different publishers — keep only ONE. Choose the entry with the most complete summary. Merge all source_references from the discarded copies into the surviving one. Set "keep": false and "drop_reason": "duplicate of _idx N" on every discarded copy.

RULE 2 - QUALITY FILTER
Set "keep": false and provide a clear "drop_reason" for any candidate that is:
- Viral social media gossip (TikTok, Instagram Reels, dance videos, MMS clips)
- Sensational rumours about local officials or celebrities with no credible named source
- Minor domestic crimes, petty theft, local brawls, or eve-teasing incidents with no wider significance
- Unverified "sources say" or "insiders claim" stories where source_references has no named publisher
- Purely speculative or opinion pieces with no factual news event

RULE 3 - GEOGRAPHIC BALANCE
If more than 2 candidates share the same geo_city value, mark the lowest-scoring extras (by freshness_score) as "keep": false with "drop_reason": "geo_city quota exceeded". {$stateBalanceRule} Candidates with null geo_city/geo_state are exempt from this rule (they are treated as national/international stories).

RULE 4 - GEO NORMALISATION
For every kept candidate: if geo_city or geo_state is null but can be confidently inferred from the title or summary, fill it in. If uncertain, leave it null.

OUTPUT FORMAT:
Return a JSON object with a top-level "results" array. For every input candidate:
  Kept    → {"_idx": N, "keep": true,  "drop_reason": null, "title": "...", "summary": "...", "geo_city": "...", "geo_state": "..."}
  Dropped → {"_idx": N, "keep": false, "drop_reason": "reason"}
Every input _idx MUST appear exactly once. You may improve title/summary wording for kept candidates.

INPUT CANDIDATES:
{$candidateJson}
PROMPT;
    }

    /**
     * Parse the LLM's JSON response and map results back to original candidates.
     *
     * Safety-net: if the response cannot be parsed, returns the original
     * candidates unchanged so no data is ever lost on a bad LLM response.
     *
     * @param  array<int, array>  $originalCandidates
     * @return array{candidates: array<int, array>, drop_reasons: array<int, string>}
     */
    private function parseRefinedCandidates(string $text, array $originalCandidates): array
    {
        $fallback = ['candidates' => $originalCandidates, 'drop_reasons' => []];

        $text = trim($text);

        // Primary path: native JSON mode guarantees the response IS already
        // a valid JSON string — go straight to json_decode, no extraction needed.
        $decoded = json_decode($text, true);

        // Fallback path: provider does not support structured output (e.g. Ollama),
        // or returned markdown fences around the JSON. Attempt manual extraction.
        if (! is_array($decoded)) {
            $text = preg_replace('/^```(?:json)?\s*/m', '', $text) ?? $text;
            $text = preg_replace('/^```\s*$/m', '', $text) ?? $text;
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            $decoded = ($start !== false && $end !== false && $end > $start)
                ? json_decode(substr($text, $start, $end - $start + 1), true)
                : null;
        }

        if (! is_array($decoded) || ! isset($decoded['results']) || ! is_array($decoded['results'])) {
            Log::warning('LLMCandidateRefinementService: could not parse JSON response, using raw candidates.', [
                'json_error' => json_last_error_msg(),
                'preview' => mb_substr($text, 0, 300),
            ]);

            return $fallback;
        }

        // Index LLM results by _idx for O(1) lookup
        $resultMap = [];
        foreach ($decoded['results'] as $item) {
            if (isset($item['_idx'])) {
                $resultMap[(int) $item['_idx']] = $item;
            }
        }

        $kept = [];
        $dropReasons = [];
        foreach ($originalCandidates as $idx => $candidate) {
            $llmResult = $resultMap[$idx] ?? null;

            // Safety net: LLM omitted this candidate entirely — keep it as-is
            if ($llmResult === null) {
                Log::debug('LLMCandidateRefinementService: candidate not in LLM results, keeping as-is.', [
                    'idx' => $idx,
                ]);
                $kept[] = $candidate;

                continue;
            }

            if (($llmResult['keep'] ?? true) === false) {
                $reason = (string) ($llmResult['drop_reason'] ?? 'dropped by editorial AI');
                $dropReasons[] = "[{$idx}] ".mb_substr($reason, 0, 120);
                Log::info('LLMCandidateRefinementService: candidate dropped by LLM.', [
                    'idx' => $idx,
                    'title' => mb_substr((string) ($candidate['title'] ?? ''), 0, 80),
                    'reason' => $reason,
                ]);

                continue;
            }

            // Merge LLM improvements into the original candidate.
            // Only title, summary, geo_city, and geo_state may be updated —
            // all other fields (scores, keywords, source_references) are
            // preserved exactly from the original to prevent hallucination drift.
            $merged = $candidate;

            if (! empty($llmResult['title']) && is_string($llmResult['title'])) {
                $merged['title'] = mb_substr(trim($llmResult['title']), 0, 200);
            }
            if (! empty($llmResult['summary']) && is_string($llmResult['summary'])) {
                $merged['summary'] = trim($llmResult['summary']);
            }
            if (array_key_exists('geo_city', $llmResult)) {
                $v = $llmResult['geo_city'];
                $merged['geo_city'] = ($v !== null && $v !== '' && strtolower((string) $v) !== 'null')
                    ? mb_substr(trim((string) $v), 0, 100)
                    : null;
            }
            if (array_key_exists('geo_state', $llmResult)) {
                $v = $llmResult['geo_state'];
                $merged['geo_state'] = ($v !== null && $v !== '' && strtolower((string) $v) !== 'null')
                    ? mb_substr(trim((string) $v), 0, 100)
                    : null;
            }

            // Merge source_references if the LLM combined duplicates into this entry.
            // Bug Fix #3: SORT_REGULAR compares arrays by value, not by URL uniqueness.
            // Deduplicate by URL key so fabricated/duplicate source entries cannot accumulate.
            if (! empty($llmResult['source_references']) && is_array($llmResult['source_references'])) {
                $existing = (array) ($merged['source_references'] ?? []);
                $allSources = array_merge($existing, $llmResult['source_references']);
                $byUrl = [];
                foreach ($allSources as $src) {
                    if (! is_array($src)) {
                        continue;
                    }
                    $url = trim((string) ($src['url'] ?? ''));
                    // Only keep sources with a real HTTP URL — reject placeholder/fabricated ones
                    if ($url !== '' && str_starts_with($url, 'http')) {
                        $byUrl[$url] = $src; // last one wins if URL repeats
                    }
                }
                $merged['source_references'] = array_values($byUrl);
            }

            $kept[] = $merged;
        }

        Log::info('LLMCandidateRefinementService: parsing complete.', [
            'input_count' => count($originalCandidates),
            'kept_count' => count($kept),
            'dropped_count' => count($dropReasons),
        ]);

        return ['candidates' => $kept, 'drop_reasons' => $dropReasons];
    }
}
