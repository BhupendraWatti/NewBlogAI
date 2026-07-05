<?php

use App\Modules\AIProviderManager\Controllers\AIProviderController;
use App\Modules\AuthManager\Controllers\AuthController;
use App\Modules\AuthManager\Controllers\UserController;
use App\Modules\ContentGeneration\Controllers\GeneratedContentController;
use App\Modules\ContentPipeline\Controllers\PipelineController;
use App\Modules\CustomerManager\Controllers\CustomerController;
use App\Modules\Licensing\Controllers\LicenseController;
use App\Modules\Operations\Controllers\OperationsController;
use App\Modules\PromptManager\Controllers\PromptController;
use App\Modules\Publishing\Controllers\PublishingController;
use App\Modules\ScheduleManager\Controllers\ScheduleController;
use App\Modules\SiteManager\Controllers\SiteController;
use App\Modules\SiteManager\Controllers\WPPluginAPIController;
use App\Modules\SubscriptionManager\Controllers\PlanController;
use App\Modules\SubscriptionManager\Controllers\SubscriptionController;
use App\Modules\SystemSettings\Controllers\SystemSettingsController;
use App\Modules\TopicManager\Controllers\TopicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::middleware('web')->group(function () {
        // Auth Routes
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::middleware('auth')->group(function () {
            Route::post('auth/logout', [AuthController::class, 'logout']);
            Route::get('auth/me', [AuthController::class, 'me']);
            Route::put('auth/profile', [AuthController::class, 'updateProfile']);
            Route::put('auth/password', [AuthController::class, 'changePassword']);
        });

        // System Settings Routes (SuperAdmin/Admin only)
        Route::middleware(['auth', 'role:1,2,3'])->group(function () {
            Route::get('settings', [SystemSettingsController::class, 'index']);
            Route::post('settings', [SystemSettingsController::class, 'update']);
        });

        // AI Providers Routes (SuperAdmin/Admin only)
        Route::middleware(['auth', 'role:1,2,3'])->group(function () {
            Route::post('providers/{provider}/test-connection', [AIProviderController::class, 'testConnection']);
            Route::post('providers/{provider}/set-default', [AIProviderController::class, 'setDefault']);
            Route::apiResource('providers', AIProviderController::class);
        });

        // Website Management Routes
        Route::middleware('auth')->group(function () {
            Route::post('sites/{id}/validate', [SiteController::class, 'validateConnection']);
            Route::post('sites/{id}/set-default', [SiteController::class, 'setDefault']);
            Route::apiResource('sites', SiteController::class);
            Route::post('sites/{id}/sync', [SiteController::class, 'sync']);
        });

        // Topic Management Routes
        Route::middleware('auth')->group(function () {
            Route::post('topics/{id}/restore', [TopicController::class, 'restore']);
            Route::apiResource('topics', TopicController::class);
        });

        // Content Pipeline Routes
        Route::middleware('auth')->group(function () {
            Route::post('pipelines/{id}/execute', [PipelineController::class, 'execute']);
            Route::post('pipelines/runs/{run}/retry', [PipelineController::class, 'retry']);
            Route::post('pipelines/runs/{run}/cancel', [PipelineController::class, 'cancel']);
            Route::get('pipelines/{id}/history', [PipelineController::class, 'history']);
            Route::apiResource('pipelines', PipelineController::class);
            Route::apiResource('schedules', ScheduleController::class);
        });

        // AI Content Generation Engine Routes
        Route::middleware('auth')->group(function () {
            Route::get('ai/logs', [GeneratedContentController::class, 'logs']);
            Route::put('articles/{id}/status', [GeneratedContentController::class, 'updateStatus']);
            Route::get('articles/{id}/revisions', [GeneratedContentController::class, 'revisions']);
            Route::apiResource('articles', GeneratedContentController::class)->except(['store', 'destroy']);
        });

        // Publishing Engine Routes
        Route::middleware('auth')->group(function () {
            Route::get('publishing/logs', [PublishingController::class, 'index']);
            Route::get('publishing/logs/{id}', [PublishingController::class, 'show']);
            Route::post('articles/{id}/publish', [PublishingController::class, 'publish']);
            Route::post('publishing/bulk', [PublishingController::class, 'bulkPublish']);
            Route::post('publishing/logs/{id}/retry', [PublishingController::class, 'retry']);
            Route::post('publishing/logs/{id}/cancel', [PublishingController::class, 'cancel']);
            Route::post('publishing/logs/{id}/sync', [PublishingController::class, 'sync']);
        });

        // Operations & Monitoring Routes
        Route::get('health', [OperationsController::class, 'health']);
        Route::middleware('auth')->group(function () {
            Route::get('analytics/ai', [OperationsController::class, 'aiStats']);
            Route::get('analytics/content', [OperationsController::class, 'contentStats']);
            Route::get('operations/audit', [OperationsController::class, 'auditLogs']);
            Route::get('operations/jobs', [OperationsController::class, 'jobLogs']);
            Route::post('operations/jobs/{id}/retry', [OperationsController::class, 'retryJob']);
            Route::apiResource('users', UserController::class);
        });

        // WordPress Plugin Licensing REST API (Rate-limited)
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('license/verify', [LicenseController::class, 'verify']);
            Route::post('license/activate', [LicenseController::class, 'activate']);
            Route::post('license/deactivate', [LicenseController::class, 'deactivate']);
        });

        Route::middleware('auth')->group(function () {
            Route::apiResource('prompts', PromptController::class);

            // Customer & Plan Management (SuperAdmin/Support only)
            Route::middleware('role:1,3')->group(function () {
                // Customer Management Module
                Route::apiResource('customers', CustomerController::class);
                Route::post('customers/{id}/restore', [CustomerController::class, 'restore']);
                Route::post('customers/{id}/archive', [CustomerController::class, 'archive']);
                Route::post('customers/{id}/notes', [CustomerController::class, 'storeNote']);
                Route::get('customers/{id}/timeline', [CustomerController::class, 'timeline']);

                // Subscription & Plan Management Module
                Route::apiResource('plans', PlanController::class);
                Route::get('customers/{id}/subscription', [SubscriptionController::class, 'show']);
                Route::post('customers/{id}/subscription', [SubscriptionController::class, 'store']);
                Route::post('customers/{id}/subscription/upgrade', [SubscriptionController::class, 'upgrade']);
                Route::post('customers/{id}/subscription/downgrade', [SubscriptionController::class, 'downgrade']);
                Route::post('customers/{id}/subscription/pause', [SubscriptionController::class, 'pause']);
                Route::post('customers/{id}/subscription/resume', [SubscriptionController::class, 'resume']);
                Route::post('customers/{id}/subscription/cancel', [SubscriptionController::class, 'cancel']);
                Route::get('customers/{id}/subscription/history', [SubscriptionController::class, 'history']);
            });
        });
    });

});

$pluginRoutes = static function (): void {
    $controller = WPPluginAPIController::class;

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
