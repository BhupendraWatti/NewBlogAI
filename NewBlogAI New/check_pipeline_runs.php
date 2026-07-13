<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\ContentPipeline\Models\PipelineRun;

$failedRuns = PipelineRun::where('status', 'failed')
    ->orWhereNotNull('error_message')
    ->latest()
    ->limit(10)
    ->get();

if ($failedRuns->isEmpty()) {
    echo "No failed pipeline runs found in database.\n";
} else {
    foreach ($failedRuns as $run) {
        echo "========================================\n";
        echo "Run ID: {$run->id}\n";
        echo "Type: {$run->type}\n";
        echo "Status: {$run->status}\n";
        echo "Error Message: {$run->error_message}\n";
        echo "Created At: {$run->created_at}\n";
    }
}
echo "========================================\n";
