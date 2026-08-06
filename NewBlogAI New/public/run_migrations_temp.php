<?php

define('LARAVEL_START', microtime(true));

// Simple authentication token to prevent unauthorized access
if (empty($_GET['token']) || $_GET['token'] !== 'Granthinfotech@26_') {
    header('HTTP/1.1 401 Unauthorized');
    die('Unauthorized access.');
}

// Register the Composer autoloader
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

header('Content-Type: text/plain');

try {
    echo "Starting artisan migrate on remote server...\n";
    $exitCode = Artisan::call('migrate', ['--force' => True]);
    echo "Exit code: $exitCode\n";
    echo "Output:\n";
    echo Artisan::output();
} catch (\Exception $e) {
    echo "Error running migrations: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
