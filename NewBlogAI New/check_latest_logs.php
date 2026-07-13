<?php

$logFile = __DIR__ . '/storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "Log file does not exist.\n";
    exit;
}

$lines = file($logFile);
$total = count($lines);
$showCount = min($total, 500);

echo "--- Showing last {$showCount} lines of laravel.log (filtered) ---\n";
for ($i = $total - $showCount; $i < $total; $i++) {
    $line = $lines[$i];
    // Skip framework stack trace lines to keep output clean and readable
    if (preg_match('/^\s*#\d+\s+D:/', $line) || preg_match('/vendor\/laravel/', $line) || preg_match('/composer/', $line)) {
        continue;
    }
    echo $line;
}
echo "--- End of Log ---\n";
