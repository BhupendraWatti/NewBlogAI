<?php
/**
 * Quick unit test for Bug 1: validateAndCorrectFreshness() temporal logic.
 * Run: php test_freshness_validation.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function _pass(string $msg): void { echo "\033[32m[PASS]\033[0m {$msg}\n"; }
function _fail(string $msg): void { echo "\033[31m[FAIL]\033[0m {$msg}\n"; }

$today    = now()->format('Y-m-d');
$tomorrow = now()->addDay()->format('Y-m-d');
$nextWeek = now()->addWeeks(3)->format('Y-m-d');
$yesterday= now()->subDay()->format('Y-m-d');
$stale10  = now()->subDays(10)->format('Y-m-d');
$veryOld  = now()->subDays(40)->format('Y-m-d');

// Use reflection to test the private method
$service = app(\App\Modules\ContentPipeline\Services\NewsDiscoveryService::class);
$method  = new ReflectionMethod($service, 'validateAndCorrectFreshness');
$method->setAccessible(true);

// ── Test 1: Future event should be penalised to ≤35
$candidates = [['title' => 'Upcoming festival', 'freshness_score' => 98, 'event_date' => $nextWeek]];
$result = $method->invoke($service, $candidates);
$score = $result[0]['freshness_score'];
if ($score <= 35) {
    _pass("Future event capped at ≤35 (got {$score})");
} else {
    _fail("Future event NOT capped — got {$score}, expected ≤35");
}
if (($result[0]['freshness_penalty_reason'] ?? '') === 'future_event') {
    _pass("penalty_reason = 'future_event' correctly set");
} else {
    _fail("penalty_reason missing or wrong: " . ($result[0]['freshness_penalty_reason'] ?? 'null'));
}

// ── Test 2: Tomorrow's event also future
$candidates = [['title' => 'Tomorrow event', 'freshness_score' => 90, 'event_date' => $tomorrow]];
$result = $method->invoke($service, $candidates);
$score = $result[0]['freshness_score'];
if ($score <= 35) {
    _pass("Tomorrow event capped at ≤35 (got {$score})");
} else {
    _fail("Tomorrow event NOT capped — got {$score}");
}

// ── Test 3: Today's event should NOT be penalised
$candidates = [['title' => 'Today breaking news', 'freshness_score' => 95, 'event_date' => $today]];
$result = $method->invoke($service, $candidates);
$score = $result[0]['freshness_score'];
if ($score === 95) {
    _pass("Today's event score untouched (got {$score})");
} else {
    _fail("Today's event score incorrectly penalised (got {$score})");
}

// ── Test 4: Yesterday's event should NOT be penalised (within tolerance)
$candidates = [['title' => 'Yesterday news', 'freshness_score' => 80, 'event_date' => $yesterday]];
$result = $method->invoke($service, $candidates);
$score = $result[0]['freshness_score'];
if ($score === 80) {
    _pass("Yesterday's event score untouched (got {$score})");
} else {
    _fail("Yesterday event unexpectedly penalised (got {$score})");
}

// ── Test 5: 10-day-old event capped at ≤55
$candidates = [['title' => 'Stale event', 'freshness_score' => 90, 'event_date' => $stale10]];
$result = $method->invoke($service, $candidates);
$score = $result[0]['freshness_score'];
if ($score <= 55) {
    _pass("10-day-old event capped at ≤55 (got {$score})");
} else {
    _fail("10-day-old event NOT capped — got {$score}");
}

// ── Test 6: Very old event (>30 days) capped hard at ≤25
$candidates = [['title' => 'Very old event', 'freshness_score' => 85, 'event_date' => $veryOld]];
$result = $method->invoke($service, $candidates);
$score = $result[0]['freshness_score'];
if ($score <= 25) {
    _pass("Very old event (>30 days) capped at ≤25 (got {$score})");
} else {
    _fail("Very old event NOT capped hard — got {$score}");
}

// ── Test 7: Missing event_date should pass through unchanged
$candidates = [['title' => 'No date news', 'freshness_score' => 75, 'event_date' => null]];
$result = $method->invoke($service, $candidates);
$score = $result[0]['freshness_score'];
if ($score === 75) {
    _pass("Missing event_date: score untouched (got {$score})");
} else {
    _fail("Missing event_date incorrectly changed score (got {$score})");
}

echo "\n\033[1mDone.\033[0m\n";
