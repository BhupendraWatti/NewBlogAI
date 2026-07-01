<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| All workspace routes resolve to the single dashboard SPA shell.
| Navigation between workspace panels is handled client-side via
| the switchWorkspace() JavaScript router in dashboard.blade.php.
|--------------------------------------------------------------------------
*/

// Named workspace slugs served by the SPA shell
$workspaces = [
    'dashboard', 'customers', 'fleet', 'sites', 'prompts', 'topics',
    'pipeline', 'scheduler', 'providers', 'media', 'seo', 'analytics',
    'notifications', 'roles', 'billing', 'settings', 'audit', 'design',
];

foreach ($workspaces as $workspace) {
    $path = $workspace === 'dashboard' ? '/' : "/{$workspace}";
    Route::get($path, fn () => view('dashboard'))->name($workspace);
}
