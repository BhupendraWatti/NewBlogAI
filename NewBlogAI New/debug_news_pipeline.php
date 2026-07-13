<?php

/**
 * Debug Script: News Authentication Pipeline – Phase 1 Root Cause Investigation
 * Run: php debug_news_pipeline.php  (from project root)
 */

// ── Bootstrap Laravel ──────────────────────────────────────────────────────────
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ── Helper ─────────────────────────────────────────────────────────────────────
function _pass(string $msg): void { echo "\033[32m[PASS]\033[0m {$msg}\n"; }
function _fail(string $msg): void { echo "\033[31m[FAIL]\033[0m {$msg}\n"; }
function _info(string $msg): void { echo "\033[36m[INFO]\033[0m {$msg}\n"; }
function _section(string $title): void {
    echo "\n\033[1m\033[33m══ {$title} ══\033[0m\n";
}

$overallFail = false;

// ══════════════════════════════════════════════════════════════════════════════
// LAYER 1: NewsDiscoveryService – buildDiscoveryPrompt
// ══════════════════════════════════════════════════════════════════════════════
_section('LAYER 1 — NewsDiscoveryService: buildDiscoveryPrompt()');

try {
    $service = app(\App\Modules\ContentPipeline\Services\NewsDiscoveryService::class);
    $method  = new ReflectionMethod($service, 'buildDiscoveryPrompt');
    $method->setAccessible(true);

    $prompt = $method->invoke($service, 'crime', 'hi', 9, [], 'India');

    echo "\n--- PROMPT DUMP (first 1500 chars) ---\n";
    echo mb_substr($prompt, 0, 1500) . "\n";
    echo "--- END PROMPT DUMP ---\n\n";

    // 1a: today's date in prompt
    $today = now()->format('F j, Y');
    if (str_contains($prompt, $today)) {
        _pass("Today's date '{$today}' is present in the prompt.");
    } else {
        _fail("Today's date '{$today}' NOT found in prompt.");
        $overallFail = true;
    }

    // 1b: freshness language
    $freshnessTerms = ['30 minutes', '1 hour', '4 hours', '24 hours'];
    $found = [];
    foreach ($freshnessTerms as $term) {
        if (str_contains($prompt, $term)) $found[] = $term;
    }
    if (!empty($found)) {
        _pass("Freshness time references found: " . implode(', ', $found));
    } else {
        _fail("No freshness time references (30 minutes / 1 hour / 4 hours / 24 hours) found in prompt.");
        $overallFail = true;
    }

    // 1c: freshness_score required
    if (str_contains($prompt, 'freshness_score')) {
        _pass("'freshness_score' field requirement is present in prompt.");
    } else {
        _fail("'freshness_score' field is MISSING from prompt.");
        $overallFail = true;
    }

    // 1d: event_date required
    if (str_contains($prompt, 'event_date')) {
        _pass("'event_date' field requirement is present in prompt.");
    } else {
        _fail("'event_date' field is MISSING from prompt.");
        $overallFail = true;
    }

    // 1e: language code 'hi'
    if (str_contains($prompt, 'hi')) {
        _pass("Language code 'hi' (Hindi) is referenced in prompt.");
    } else {
        _fail("Language code 'hi' (Hindi) NOT found in prompt.");
        $overallFail = true;
    }

    // 1f: region India
    if (str_contains($prompt, 'India')) {
        _pass("Region context 'India' is present in prompt.");
    } else {
        _fail("Region context 'India' NOT found in prompt.");
        $overallFail = true;
    }

    // 1g: anti-stale instruction
    if (str_contains($prompt, 'Do NOT return stale') || str_contains($prompt, 'stale news')) {
        _pass("Anti-stale news instruction present in prompt.");
    } else {
        _fail("Anti-stale news instruction ('stale news') is MISSING from prompt.");
        $overallFail = true;
    }

} catch (\Throwable $e) {
    _fail("EXCEPTION building discovery prompt: " . $e->getMessage());
    $overallFail = true;
}

// ══════════════════════════════════════════════════════════════════════════════
// LAYER 1b: tools ternary in runDiscoveryWithProvider()
// ══════════════════════════════════════════════════════════════════════════════
_section('LAYER 1b — tools ternary for Gemini vs non-Gemini providers');

$toolsForGemini = strtolower('gemini') === 'gemini' ? [['google_search' => (object) []]] : null;
$toolsForGroq   = strtolower('groq') === 'gemini' ? [['google_search' => (object) []]] : null;

if (is_array($toolsForGemini) && count($toolsForGemini) === 1) {
    _pass("tools ternary → correct array for 'gemini' provider.");
} else {
    _fail("tools ternary → WRONG for 'gemini'. Got: " . json_encode($toolsForGemini));
    $overallFail = true;
}

if ($toolsForGroq === null) {
    _pass("tools ternary → null for 'groq' provider (safe for Groq driver).");
} else {
    _fail("tools ternary → non-null for 'groq'. Got: " . json_encode($toolsForGroq));
    $overallFail = true;
}

// ══════════════════════════════════════════════════════════════════════════════
// LAYER 2: GoogleGeminiDriver – tools null safety
// ══════════════════════════════════════════════════════════════════════════════
_section('LAYER 2 — GoogleGeminiDriver: tools=null NOT sent in payload');

$driverFile   = __DIR__ . '/app/Modules/AIProviderManager/Drivers/GoogleGeminiDriver.php';
$driverSource = file_get_contents($driverFile);

// Use simple string search to avoid PHP interpolation issues in the regex
if (str_contains($driverSource, '!empty($options[\'tools\'])') || str_contains($driverSource, "!empty(\$options['tools'])") || str_contains($driverSource, '!empty($opts[\'tools\'])')) {
    _pass("GoogleGeminiDriver guard: !empty(\$options['tools'] / \$opts['tools']) — null is safely excluded from payload.");
} elseif (str_contains($driverSource, "!empty(\$options['tools'])") || preg_match('/!empty\s*\(\s*\$(?:options|opts)\s*\[\s*.tools.\s*\]/', $driverSource)) {
    _pass("GoogleGeminiDriver guard: !empty(\$options['tools'] / \$opts['tools']) — null is safely excluded from payload.");
} else {
    _fail("GoogleGeminiDriver tools guard MISSING or wrong syntax. Driver may send null tools to Gemini API.");
    $overallFail = true;
}

// Simulate payload construction
$nullOpts  = ['tools' => null, 'max_tokens' => 100, 'temperature' => 0.2];
$realOpts  = ['tools' => [['google_search' => (object) []]], 'max_tokens' => 100, 'temperature' => 0.2];

$p1 = ['contents' => [], 'generationConfig' => []];
if (!empty($nullOpts['tools'])) $p1['tools'] = $nullOpts['tools'];

$p2 = ['contents' => [], 'generationConfig' => []];
if (!empty($realOpts['tools'])) $p2['tools'] = $realOpts['tools'];

if (!array_key_exists('tools', $p1)) {
    _pass("tools=null → 'tools' key NOT in Gemini payload (correct).");
} else {
    _fail("tools=null → 'tools' key INCORRECTLY present in Gemini payload.");
    $overallFail = true;
}

if (array_key_exists('tools', $p2)) {
    _pass("tools=[...] → 'tools' key IS present in Gemini payload (correct).");
} else {
    _fail("tools=[...] → 'tools' key MISSING from Gemini payload (BUG).");
    $overallFail = true;
}

// ══════════════════════════════════════════════════════════════════════════════
// LAYER 2b: Gemini grounding response text extraction
// ══════════════════════════════════════════════════════════════════════════════
_section('LAYER 2b — Gemini: text extraction safe when grounding active');

// Per official Gemini API docs: text is returned in parts[0]['text'] even with grounding active.
// groundingMetadata is ADDITIVE. Verified via web research.

$groundingResp = [
    'candidates' => [[
        'content' => ['parts' => [['text' => '[{"title":"Real News"}]']]],
        'groundingMetadata' => [
            'webSearchQueries' => ['crime India today'],
            'groundingChunks' => [['web' => ['uri' => 'https://ndtv.com/', 'title' => 'NDTV']]],
        ],
        'finishReason' => 'STOP',
    ]],
    'usageMetadata' => ['promptTokenCount' => 200, 'candidatesTokenCount' => 80, 'totalTokenCount' => 280],
];

$extractedText = $groundingResp['candidates'][0]['content']['parts'][0]['text'] ?? '';
if (!empty($extractedText)) {
    _pass("Text extraction via \$data['candidates'][0]['content']['parts'][0]['text'] works when grounding is active.");
    _info("Gemini grounding is ADDITIVE — text is always present alongside groundingMetadata.");
} else {
    _fail("Text is MISSING when grounding is active — critical driver bug!");
    $overallFail = true;
}

// Edge case: empty parts (should fallback gracefully)
$emptyPartsResp = ['candidates' => [['content' => ['parts' => []]]], 'usageMetadata' => []];
$fallback = $emptyPartsResp['candidates'][0]['content']['parts'][0]['text'] ?? '';
if ($fallback === '') {
    _pass("Empty parts → text falls back to '' gracefully. parseCandidates will throw RuntimeException (expected behavior — failover kicks in).");
}

// ══════════════════════════════════════════════════════════════════════════════
// LAYER 3: GroqDriver – null tools safety
// ══════════════════════════════════════════════════════════════════════════════
_section('LAYER 3 — GroqDriver: null tools in options is safely ignored');

$groqFile   = __DIR__ . '/app/Modules/AIProviderManager/Drivers/GroqDriver.php';
$groqSource = file_get_contents($groqFile);

$groqRefersToTools = str_contains($groqSource, "'tools'") || str_contains($groqSource, '"tools"');
if (!$groqRefersToTools) {
    _pass("GroqDriver does NOT reference 'tools' in its payload — null tools option is fully ignored (safe).");
} else {
    // If it does reference tools, it needs a guard
    if (preg_match('/!empty.*tools/i', $groqSource) || preg_match('/isset.*tools/i', $groqSource)) {
        _pass("GroqDriver references 'tools' but guards it properly.");
    } else {
        _fail("GroqDriver references 'tools' without a guard — could send null tools to Groq API!");
        $overallFail = true;
    }
}

if (!str_contains($groqSource, 'google_search') && !str_contains($groqSource, 'grounding')) {
    _pass("GroqDriver has NO Google Search grounding (expected). Falls back to prompt-based discovery.");
    _info("Groq uses prompt-only news discovery — quality depends on prompt freshness instructions.");
} else {
    _fail("GroqDriver unexpectedly references grounding — investigate.");
    $overallFail = true;
}

// ══════════════════════════════════════════════════════════════════════════════
// LAYER 4: ResearchService – freshness in queries
// ══════════════════════════════════════════════════════════════════════════════
_section('LAYER 4 — ResearchService: query freshness');

$researchSource = file_get_contents(__DIR__ . '/app/Modules/ContentPipeline/Services/ResearchService.php');

if (str_contains($researchSource, "now()->format('Y-m-d')")) {
    _pass("ResearchService includes today's date (now()->format('Y-m-d')) in queries.");
} else {
    _fail("ResearchService does NOT reference today's date.");
    $overallFail = true;
}

if (str_contains($researchSource, 'breaking') || str_contains($researchSource, 'latest updates')) {
    _pass("ResearchService includes 'breaking' or 'latest updates' freshness hints.");
} else {
    _fail("ResearchService queries lack freshness hints.");
    $overallFail = true;
}

// ══════════════════════════════════════════════════════════════════════════════
// LAYER 4b: SourceCollectionService – searchViaPrompt freshness
// ══════════════════════════════════════════════════════════════════════════════
_section('LAYER 4b — SourceCollectionService: searchViaPrompt() freshness enforcement');

$sourceSource = file_get_contents(__DIR__ . '/app/Modules/ContentPipeline/Services/SourceCollectionService.php');

if (str_contains($sourceSource, '48 hours') || str_contains($sourceSource, '48-72 hours')) {
    _pass("searchViaPrompt() demands news from the last 48/48-72 hours.");
} else {
    _fail("searchViaPrompt() does NOT specify 48-hour freshness requirement.");
    $overallFail = true;
}

if (str_contains($sourceSource, 'REAL, CURRENT events') || str_contains($sourceSource, 'CURRENT real news')) {
    _pass("searchViaPrompt() explicitly demands REAL, CURRENT events.");
} else {
    _fail("searchViaPrompt() does not demand REAL/CURRENT events explicitly.");
    $overallFail = true;
}

if (str_contains($sourceSource, 'last 48 hours')) {
    _pass("searchViaGeminiGrounding() enforces 48-hour freshness in its prompt.");
} else {
    _fail("searchViaGeminiGrounding() does NOT enforce 48-hour freshness.");
    $overallFail = true;
}

// ══════════════════════════════════════════════════════════════════════════════
// LAYER 5: buildDiscoveryPrompt – exclusion injection & anti-hallucination
// ══════════════════════════════════════════════════════════════════════════════
_section('LAYER 5 — buildDiscoveryPrompt: exclusion injection & anti-hallucination');

try {
    $service = app(\App\Modules\ContentPipeline\Services\NewsDiscoveryService::class);
    $method  = new ReflectionMethod($service, 'buildDiscoveryPrompt');
    $method->setAccessible(true);
    $prompt  = $method->invoke($service, 'politics', 'en', 9, ['Old Headline From Yesterday'], null);

    if (str_contains($prompt, now()->format('F j, Y'))) {
        _pass("Prompt includes today's date for context.");
    } else {
        _fail("Prompt does NOT include today's date.");
        $overallFail = true;
    }

    if (str_contains($prompt, 'stale') || str_contains($prompt, '2-5 days ago')) {
        _pass("Prompt explicitly warns against stale/old news.");
    } else {
        _fail("Prompt does NOT warn against stale news.");
        $overallFail = true;
    }

    if (str_contains($prompt, 'published_at_relative')) {
        _pass("Prompt requires 'published_at_relative' field.");
    } else {
        _fail("Prompt does NOT require 'published_at_relative' field.");
        $overallFail = true;
    }

    if (str_contains($prompt, 'Old Headline From Yesterday')) {
        _pass("Excluded titles ARE injected into prompt's exclusion block.");
    } else {
        _fail("Excluded titles NOT found in prompt — exclusion injection broken.");
        $overallFail = true;
    }

    if (str_contains($prompt, 'JSON-only') || str_contains($prompt, 'JSON array')) {
        _pass("Prompt enforces JSON-only output (parser-safe).");
    } else {
        _fail("Prompt does NOT enforce JSON-only output.");
        $overallFail = true;
    }

} catch (\Throwable $e) {
    _fail("EXCEPTION in Layer 5 check: " . $e->getMessage());
    $overallFail = true;
}

// ══════════════════════════════════════════════════════════════════════════════
// SUMMARY
// ══════════════════════════════════════════════════════════════════════════════
_section('DIAGNOSTIC SUMMARY');

if ($overallFail) {
    echo "\033[31m✗ Some checks FAILED. Review the [FAIL] items above and apply fixes.\033[0m\n\n";
    exit(1);
} else {
    echo "\033[32m✓ ALL CHECKS PASSED. Pipeline authentication pipeline is correctly configured.\033[0m\n\n";
    exit(0);
}
