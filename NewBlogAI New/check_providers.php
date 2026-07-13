<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\AIProviderManager\Models\AIProvider;

$providers = AIProvider::whereIn('provider_key', ['gemini', 'groq'])->get();

foreach ($providers as $p) {
    echo "========================================\n";
    echo "Provider: {$p->name} ({$p->provider_key})\n";
    echo "Enabled: " . ($p->is_enabled ? 'Yes' : 'No') . "\n";
    echo "Default: " . ($p->is_default ? 'Yes' : 'No') . "\n";
    echo "Credits Remaining: " . ($p->credits_remaining !== null ? number_format($p->credits_remaining) : 'N/A') . "\n";
    echo "Credits Total (Limit): " . ($p->credits_total !== null ? number_format($p->credits_total) : 'N/A') . "\n";
    
    if ($p->reset_at) {
        $secondsLeft = strtotime($p->reset_at->toDateTimeString()) - time();
        if ($secondsLeft > 0) {
            $h = floor($secondsLeft / 3600);
            $m = floor(($secondsLeft % 3600) / 60);
            $s = $secondsLeft % 60;
            echo "Reset In: {$h}h {$m}m {$s}s (at {$p->reset_at->toDateTimeString()})\n";
        } else {
            echo "Reset In: Already reset / ready (at {$p->reset_at->toDateTimeString()})\n";
        }
    } else {
        echo "Reset In: No active reset timer\n";
    }
    
    echo "Last Error: " . ($p->last_error ?: 'None') . "\n";
}
echo "========================================\n";
