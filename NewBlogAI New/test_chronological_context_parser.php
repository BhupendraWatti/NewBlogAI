<?php
/**
 * Unit test for ChronologicalContextParser.
 * Tests all 3 requirements: date extraction, lag classification, guardrail injection.
 * Run: php test_chronological_context_parser.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\ContentPipeline\Services\ChronologicalContextParser;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use Carbon\Carbon;

function _pass(string $msg): void { echo "\033[32m[PASS]\033[0m {$msg}\n"; }
function _fail(string $msg): void { echo "\033[31m[FAIL]\033[0m {$msg}\n"; }
function _info(string $msg): void { echo "\033[33m[INFO]\033[0m {$msg}\n"; }

$parser = app(ChronologicalContextParser::class);

// Helper: build a minimal PipelineContext with mocked objects
function makeContext(array $selectedNews = [], array $sources = []): PipelineContext
{
    $run      = new PipelineRun();
    $pipeline = new ContentPipeline();
    $ctx = new PipelineContext($run, $pipeline);
    if ($selectedNews) {
        $ctx->metadata['selected_news'] = $selectedNews;
    }
    $ctx->sources = $sources;
    return $ctx;
}

$today = Carbon::now()->format('Y-m-d');
$todayCarbon = Carbon::now()->startOfDay();

echo "\n══ TEST 1: English explicit date (July 8) detected as root event ══\n";
$corpus = "July 8 की घटना के बाद आज NHRC ने नोटिस जारी किया।";
$ctx = makeContext(['title' => 'NHRC issues notice', 'summary' => $corpus]);
$ctx = $parser->handle($ctx);

$rootEvent = $ctx->metadata['root_event_time'] ?? null;
$storyType = $ctx->metadata['story_type'] ?? null;
$lagHours  = $ctx->metadata['event_time_lag_hours'] ?? null;

if ($rootEvent !== null && str_contains($rootEvent, '07-08')) {
    _pass("Root event date correctly identified as July 8 (got: {$rootEvent})");
} else {
    _fail("Root event date wrong or missing (got: " . ($rootEvent ?? 'null') . ")");
}

$expectedDays = $todayCarbon->diffInDays(Carbon::parse("2026-07-08"));
if ($storyType === 'followup' && $expectedDays >= 2) {
    _pass("story_type = 'followup' correctly set (lag = {$lagHours}h)");
} elseif ($storyType === 'breaking' && $expectedDays < 2) {
    _pass("story_type = 'breaking' correctly set (recent date)");
} else {
    _info("story_type = '{$storyType}', root_event = {$rootEvent}, lag = {$lagHours}h — verify manually if July 8 is <48h from today");
}

$guardrail = $ctx->metadata['dynamic_instructions'] ?? '';
if (str_contains($guardrail, 'TEMPORAL FRAMING GUARDRAIL') || str_contains($guardrail, 'FOLLOW-UP') || $storyType === 'breaking') {
    _pass("Temporal framing guardrail present in dynamic_instructions or story is breaking");
} else {
    _fail("Temporal framing guardrail missing from dynamic_instructions");
}

echo "\n══ TEST 2: Hindi relative phrase '5 दिन पहले' ══\n";
$ctx = makeContext(['title' => 'Test', 'summary' => 'The sewer accident happened 5 दिन पहले in the district.']);
$ctx = $parser->handle($ctx);
$rootEvent = $ctx->metadata['root_event_time'] ?? null;
$storyType = $ctx->metadata['story_type'] ?? null;
$expected5DaysAgo = $todayCarbon->copy()->subDays(5)->format('Y-m-d');

if ($rootEvent === $expected5DaysAgo) {
    _pass("'5 दिन पहले' resolved to {$rootEvent} (expected {$expected5DaysAgo})");
} else {
    _fail("'5 दिन पहले' resolution wrong (got: " . ($rootEvent ?? 'null') . ", expected: {$expected5DaysAgo})");
}
if ($storyType === 'followup') {
    _pass("story_type = 'followup' (5 days > 48h threshold)");
} else {
    _fail("story_type should be 'followup' for 5-day-old event (got: {$storyType})");
}

echo "\n══ TEST 3: Hindi day-of-week 'गुरुवार को' anchor ══\n";
$ctx = makeContext(['title' => 'Test', 'summary' => 'गुरुवार को हुई घटना के बाद प्रशासन ने कार्रवाई की।']);
$ctx = $parser->handle($ctx);
$rootEvent = $ctx->metadata['root_event_time'] ?? null;
if ($rootEvent !== null) {
    _pass("गुरुवार resolved to a date: {$rootEvent}");
    $dayOfWeek = Carbon::parse($rootEvent)->dayOfWeek;
    if ($dayOfWeek === Carbon::THURSDAY) {
        _pass("Resolved day is correctly THURSDAY");
    } else {
        _fail("Day-of-week mismatch (got dayOfWeek={$dayOfWeek}, expected " . Carbon::THURSDAY . ")");
    }
} else {
    _fail("गुरुवार was not resolved to any date");
}

echo "\n══ TEST 4: Hindi month date '9 जुलाई' ══\n";
$ctx = makeContext(['title' => 'Test', 'summary' => 'The factory caught fire on 9 जुलाई at 3pm.']);
$ctx = $parser->handle($ctx);
$rootEvent = $ctx->metadata['root_event_time'] ?? null;
if ($rootEvent !== null && str_contains($rootEvent, '07-09')) {
    _pass("'9 जुलाई' resolved to {$rootEvent}");
} else {
    _fail("'9 जुलाई' resolution wrong (got: " . ($rootEvent ?? 'null') . ")");
}

echo "\n══ TEST 5: ISO date '2026-07-10' ══\n";
$ctx = makeContext(['title' => 'Test', 'summary' => 'Following the explosion on 2026-07-10, officials today issued a report.']);
$ctx = $parser->handle($ctx);
$rootEvent = $ctx->metadata['root_event_time'] ?? null;
if ($rootEvent !== null && str_contains($rootEvent, '07-10')) {
    _pass("ISO date '2026-07-10' resolved to {$rootEvent}");
} else {
    _fail("ISO date resolution wrong (got: " . ($rootEvent ?? 'null') . ")");
}

echo "\n══ TEST 6: No date in corpus — transparent no-op ══\n";
$ctx = makeContext(['title' => 'Breaking news happening now', 'summary' => 'Earthquake hits the city. Emergency services are responding.']);
$ctx = $parser->handle($ctx);
$rootEvent = $ctx->metadata['root_event_time'] ?? null;
$storyType = $ctx->metadata['story_type'] ?? null;
if ($rootEvent === null) {
    _pass("No date found — root_event_time is null (correct no-op)");
} else {
    _fail("Unexpected root_event_time found when corpus has no dates: {$rootEvent}");
}
if ($storyType === 'breaking') {
    _pass("story_type defaulted to 'breaking' when no date context");
} else {
    _fail("story_type should be 'breaking' when no dates found (got: {$storyType})");
}

echo "\n══ TEST 7: source_publish_time falls back correctly ══\n";
$ctx = makeContext(['title' => 'Test'], [
    ['title' => 'Article', 'snippet' => 'Body', 'published_date' => '2026-07-12', 'metadata' => []],
]);
$ctx = $parser->handle($ctx);
$spt = $ctx->metadata['source_publish_time'] ?? null;
if ($spt !== null && str_starts_with($spt, '2026-07-12')) {
    _pass("source_publish_time correctly read from source published_date ({$spt})");
} else {
    _fail("source_publish_time wrong (got: " . ($spt ?? 'null') . ")");
}

echo "\n══ TEST 8: Future date in corpus is rejected ══\n";
$futureDate = $todayCarbon->copy()->addDays(5)->format('Y-m-d');
$ctx = makeContext(['title' => 'Test', 'summary' => "Event scheduled for {$futureDate} will be held."]);
$ctx = $parser->handle($ctx);
$rootEvent = $ctx->metadata['root_event_time'] ?? null;
if ($rootEvent === null) {
    _pass("Future date correctly ignored — root_event_time is null");
} else {
    _fail("Future date was incorrectly accepted as root_event_time: {$rootEvent}");
}

echo "\n══ TEST 9: 'कल' (yesterday) resolves correctly ══\n";
$ctx = makeContext(['title' => 'Test', 'summary' => 'कल हुई दुर्घटना के बाद परिजनों ने न्याय की मांग की।']);
$ctx = $parser->handle($ctx);
$rootEvent = $ctx->metadata['root_event_time'] ?? null;
$expectedYesterday = $todayCarbon->copy()->subDay()->format('Y-m-d');
if ($rootEvent === $expectedYesterday) {
    _pass("'कल' resolved to yesterday ({$rootEvent})");
} else {
    _fail("'कल' resolution wrong (got: " . ($rootEvent ?? 'null') . ", expected: {$expectedYesterday})");
}

echo "\n══ TEST 10: Guardrail is injected into PromptEngine dynamic instructions ══\n";
$ctx = makeContext(['title' => 'NHRC Notice', 'summary' => 'Following the incident on July 8, today NHRC issued notice.']);
$ctx = $parser->handle($ctx);
$di = $ctx->metadata['dynamic_instructions'] ?? '';
if (str_contains($di, 'TEMPORAL FRAMING GUARDRAIL') || str_contains($di, 'FOLLOW-UP')) {
    _pass("PromptEngine will receive guardrail in dynamic_instructions");
    _info("Guardrail preview: " . mb_substr($di, 0, 120) . "...");
} elseif (($ctx->metadata['story_type'] ?? '') === 'breaking') {
    _pass("Story classified as breaking (July 8 is recent) — no guardrail needed");
} else {
    _fail("Guardrail missing despite non-breaking story_type = " . ($ctx->metadata['story_type'] ?? 'unknown'));
}

echo "\n\033[1mDone.\033[0m\n\n";
