# Bug Report: `Discovery Failed` — Failed to queue coverage discovery run

**Date:** 2026-07-09
**Severity:** Critical (blocks entire Newsroom workflow)
**Status:** Fixed and Pushed (commit 20b4ac2)

---

## Symptom

When clicking **Discover Top News Stories** in the Newsroom Pipeline UI, the following error appeared:

> Discovery Failed  
> Failed to queue coverage discovery run.

---

## Reproduction Steps

1. Go to **Content Pipeline** workspace in the dashboard
2. Fill in: Site → Provider (Gemini 2.5 Flash) → Topic: Politics → Country: India → Template → Language
3. Click **Discover Top News Stories**
4. Error dialog appears immediately within 2-3 seconds

---

## Root Cause Analysis

### Evidence from storage/logs/laravel.log

`
[2026-07-09 06:47:24] local.ERROR: Failed to trigger coverage discovery run:
  Discovery response did not contain a JSON array.

[2026-07-09 06:47:24] local.ERROR: Failed to queue coverage discovery run.
  {PipelineService.php:156}
`

The actual failure was inside `NewsDiscoveryService::parseCandidates()`, not in the queue.
Because `QUEUE_CONNECTION=sync`, the job ran **synchronously** inside `DB::transaction()`,
so the exception bubbled up through `PipelineService::triggerDiscovery()` and became the API error.

### Root Cause 1 — Truncated Response (Primary)

`NewsDiscoveryService::discover()` called:

`php
// No options passed — defaults to maxOutputTokens: 2048
`dollar`result = `dollar`driver->generate(`dollar`provider->api_key, `dollar`promptText, `dollar`provider->default_model);
`

**GoogleGeminiDriver** defaulted to `maxOutputTokens: 2048`.
Asking for **12 JSON objects** (each with title, summary, source_references, keywords, scores)
easily consumes 4,000-6,000 tokens. Gemini **truncated the response mid-JSON**, producing
malformed output that `json_decode()` rejected with "did not contain a JSON array."

### Root Cause 2 — Thinking-Model Preamble

Gemini 2.5 Flash is a **thinking model**. Even when asked for JSON-only output,
it sometimes prefixes the response with reasoning text before the array.
The old prompt did not explicitly forbid this.

### Root Cause 3 — OVERGENERATION_COUNT = 12 (too large)

Requesting 12 objects increased token usage further, making truncation more likely.

---

## Fixes Applied

### File: app/Modules/ContentPipeline/Services/NewsDiscoveryService.php

#### Fix 1 — Raise token budget to 8192

`php
private const DISCOVERY_MAX_TOKENS = 8192;

`dollar`result = `dollar`driver->generate(
    `dollar`provider->api_key,
    `dollar`promptText,
    `dollar`provider->default_model,
    [
        'max_tokens'  => self::DISCOVERY_MAX_TOKENS,
        'temperature' => 0.2,
    ]
);
`

#### Fix 2 — Reduce OVERGENERATION_COUNT to 9

`php
public const OVERGENERATION_COUNT = 9; // was 12 — same as CANDIDATE_TARGET
`

#### Fix 3 — Harden parseCandidates() for preamble and truncation

- Find FIRST `[` to skip thinking-model preamble text
- Find LAST `]` and attempt recovery by closing at last `}` if truncated
- Lenient second-pass recovery: strip after last `}` and retry json_decode
- Log detailed errors with response preview for easier diagnosis

#### Fix 4 — Rewrite discovery prompt for JSON-only output

`
You are a JSON-only news data API.
STRICT OUTPUT RULES:
- Your ENTIRE response must be a single valid JSON array starting with [ and ending with ]
- Do NOT write any text before or after the JSON array
- Do NOT use markdown code fences
`

---

## Testing Verification

1. Go to Content Pipeline → fill the form → click Discover Top News Stories
2. Expect: spinner shows "AI is searching the web..." for ~10-20 seconds
3. Expect: 9 news candidate cards appear (Step 2 grid)
4. Click Generate Article on any card → full article appears in Step 3

### If still failing, check laravel.log for:

- `parseCandidates succeeded` — JSON parsed OK
- `No JSON array found` — API returned no JSON at all (check API key)
- `JSON decode failed after recovery` — response is present but malformed (increase max_tokens further)

### API Key Check

Make sure the API key is the **full key** in database, not the masked form `ABC.....xyz`.
The masked form is display-only — save the full key in AI Providers settings.
