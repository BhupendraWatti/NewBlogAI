<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::middleware('web')->group(function () {
        // Auth Routes
        Route::post('auth/login', [\App\Modules\AuthManager\Controllers\AuthController::class, 'login']);
        Route::middleware('auth')->group(function () {
            Route::post('auth/logout', [\App\Modules\AuthManager\Controllers\AuthController::class, 'logout']);
            Route::get('auth/me', [\App\Modules\AuthManager\Controllers\AuthController::class, 'me']);
            Route::put('auth/profile', [\App\Modules\AuthManager\Controllers\AuthController::class, 'updateProfile']);
            Route::put('auth/password', [\App\Modules\AuthManager\Controllers\AuthController::class, 'changePassword']);
        });

        // System Settings Routes
        Route::middleware('auth')->group(function () {
            Route::get('settings', [\App\Modules\SystemSettings\Controllers\SystemSettingsController::class, 'index']);
            Route::post('settings', [\App\Modules\SystemSettings\Controllers\SystemSettingsController::class, 'update']);
        });

        // AI Providers Routes
        Route::middleware('auth')->group(function () {
            Route::post('providers/{provider}/test-connection', [\App\Modules\AIProviderManager\Controllers\AIProviderController::class, 'testConnection']);
            Route::post('providers/{provider}/set-default', [\App\Modules\AIProviderManager\Controllers\AIProviderController::class, 'setDefault']);
            Route::apiResource('providers', \App\Modules\AIProviderManager\Controllers\AIProviderController::class);
        });

        // Website Management Routes
        Route::middleware('auth')->group(function () {
            Route::post('sites/{id}/validate', [\App\Modules\SiteManager\Controllers\SiteController::class, 'validateConnection']);
            Route::post('sites/{id}/set-default', [\App\Modules\SiteManager\Controllers\SiteController::class, 'setDefault']);
            Route::apiResource('sites', \App\Modules\SiteManager\Controllers\SiteController::class);
            Route::post('sites/{id}/sync', [\App\Modules\SiteManager\Controllers\SiteController::class, 'sync']);
        });

        // Topic Management Routes
        Route::middleware('auth')->group(function () {
            Route::post('topics/{id}/restore', [\App\Modules\TopicManager\Controllers\TopicController::class, 'restore']);
            Route::apiResource('topics', \App\Modules\TopicManager\Controllers\TopicController::class);
        });

        // Content Pipeline Routes
        Route::middleware('auth')->group(function () {
            Route::post('pipelines/{id}/execute', [\App\Modules\ContentPipeline\Controllers\PipelineController::class, 'execute']);
            Route::post('pipelines/runs/{run}/retry', [\App\Modules\ContentPipeline\Controllers\PipelineController::class, 'retry']);
            Route::post('pipelines/runs/{run}/cancel', [\App\Modules\ContentPipeline\Controllers\PipelineController::class, 'cancel']);
            Route::get('pipelines/{id}/history', [\App\Modules\ContentPipeline\Controllers\PipelineController::class, 'history']);
            Route::apiResource('pipelines', \App\Modules\ContentPipeline\Controllers\PipelineController::class);
            Route::apiResource('schedules', \App\Modules\ScheduleManager\Controllers\ScheduleController::class);
        });

        // AI Content Generation Engine Routes
        Route::middleware('auth')->group(function () {
            Route::get('ai/logs', [\App\Modules\ContentGeneration\Controllers\GeneratedContentController::class, 'logs']);
            Route::put('articles/{id}/status', [\App\Modules\ContentGeneration\Controllers\GeneratedContentController::class, 'updateStatus']);
            Route::get('articles/{id}/revisions', [\App\Modules\ContentGeneration\Controllers\GeneratedContentController::class, 'revisions']);
            Route::apiResource('articles', \App\Modules\ContentGeneration\Controllers\GeneratedContentController::class)->except(['store', 'destroy']);
        });

        // Publishing Engine Routes
        Route::middleware('auth')->group(function () {
            Route::get('publishing/logs', [\App\Modules\Publishing\Controllers\PublishingController::class, 'index']);
            Route::get('publishing/logs/{id}', [\App\Modules\Publishing\Controllers\PublishingController::class, 'show']);
            Route::post('articles/{id}/publish', [\App\Modules\Publishing\Controllers\PublishingController::class, 'publish']);
            Route::post('publishing/bulk', [\App\Modules\Publishing\Controllers\PublishingController::class, 'bulkPublish']);
            Route::post('publishing/logs/{id}/retry', [\App\Modules\Publishing\Controllers\PublishingController::class, 'retry']);
            Route::post('publishing/logs/{id}/cancel', [\App\Modules\Publishing\Controllers\PublishingController::class, 'cancel']);
            Route::post('publishing/logs/{id}/sync', [\App\Modules\Publishing\Controllers\PublishingController::class, 'sync']);
        });

        // Operations & Monitoring Routes
        Route::get('health', [\App\Modules\Operations\Controllers\OperationsController::class, 'health']);
        Route::middleware('auth')->group(function () {
            Route::get('analytics/ai', [\App\Modules\Operations\Controllers\OperationsController::class, 'aiStats']);
            Route::get('analytics/content', [\App\Modules\Operations\Controllers\OperationsController::class, 'contentStats']);
            Route::get('operations/audit', [\App\Modules\Operations\Controllers\OperationsController::class, 'auditLogs']);
            Route::get('operations/jobs', [\App\Modules\Operations\Controllers\OperationsController::class, 'jobLogs']);
            Route::post('operations/jobs/{id}/retry', [\App\Modules\Operations\Controllers\OperationsController::class, 'retryJob']);
            Route::apiResource('users', \App\Modules\AuthManager\Controllers\UserController::class);
        });

        // WordPress Plugin Licensing REST API (Rate-limited)
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('license/verify', [\App\Modules\Licensing\Controllers\LicenseController::class, 'verify']);
            Route::post('license/activate', [\App\Modules\Licensing\Controllers\LicenseController::class, 'activate']);
            Route::post('license/deactivate', [\App\Modules\Licensing\Controllers\LicenseController::class, 'deactivate']);
        });

        Route::middleware('auth')->group(function () {
            Route::apiResource('prompts', \App\Modules\PromptManager\Controllers\PromptController::class);
            
            // Customer Management Module
            Route::apiResource('customers', \App\Modules\CustomerManager\Controllers\CustomerController::class);
            Route::post('customers/{id}/restore', [\App\Modules\CustomerManager\Controllers\CustomerController::class, 'restore']);
            Route::post('customers/{id}/archive', [\App\Modules\CustomerManager\Controllers\CustomerController::class, 'archive']);
            Route::post('customers/{id}/notes', [\App\Modules\CustomerManager\Controllers\CustomerController::class, 'storeNote']);
            Route::get('customers/{id}/timeline', [\App\Modules\CustomerManager\Controllers\CustomerController::class, 'timeline']);

            // Subscription & Plan Management Module
            Route::apiResource('plans', \App\Modules\SubscriptionManager\Controllers\PlanController::class);
            Route::get('customers/{id}/subscription', [\App\Modules\SubscriptionManager\Controllers\SubscriptionController::class, 'show']);
            Route::post('customers/{id}/subscription', [\App\Modules\SubscriptionManager\Controllers\SubscriptionController::class, 'store']);
            Route::post('customers/{id}/subscription/upgrade', [\App\Modules\SubscriptionManager\Controllers\SubscriptionController::class, 'upgrade']);
            Route::post('customers/{id}/subscription/downgrade', [\App\Modules\SubscriptionManager\Controllers\SubscriptionController::class, 'downgrade']);
            Route::post('customers/{id}/subscription/pause', [\App\Modules\SubscriptionManager\Controllers\SubscriptionController::class, 'pause']);
            Route::post('customers/{id}/subscription/resume', [\App\Modules\SubscriptionManager\Controllers\SubscriptionController::class, 'resume']);
            Route::post('customers/{id}/subscription/cancel', [\App\Modules\SubscriptionManager\Controllers\SubscriptionController::class, 'cancel']);
            Route::get('customers/{id}/subscription/history', [\App\Modules\SubscriptionManager\Controllers\SubscriptionController::class, 'history']);
        });
    });

});

$pluginRoutes = static function (): void {
    $controller = \App\Modules\SiteManager\Controllers\WPPluginAPIController::class;

    Route::post('login', [$controller, 'login'])->middleware('throttle:10,1');
    Route::post('connect', [$controller, 'login'])->middleware('throttle:10,1');
    Route::post('register-website', [$controller, 'registerWebsite']);
    Route::get('configuration', [$controller, 'configuration']);
    Route::get('dashboard', [$controller, 'dashboard']);
    Route::get('status', [$controller, 'status']);
    Route::post('heartbeat', [$controller, 'heartbeat']);
    Route::post('sync', [$controller, 'sync']);
    Route::post('disconnect', [$controller, 'disconnect']);
    Route::post('refresh-token', [$controller, 'refreshToken']);
    Route::get('logs', [$controller, 'logs']);
    Route::post('publish-result', [$controller, 'publishResult']);
};

// Canonical plugin contract.
Route::prefix('v1/plugin')->middleware('throttle:120,1')->group($pluginRoutes);

// Compatibility aliases used by existing plugin releases.
Route::prefix('plugin')->middleware('throttle:120,1')->group($pluginRoutes);
