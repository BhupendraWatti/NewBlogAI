<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::apiResource('sites', \App\Modules\SiteManager\Controllers\SiteController::class);
    Route::post('sites/{id}/sync', [\App\Modules\SiteManager\Controllers\SiteController::class, 'sync']);
    Route::apiResource('prompts', \App\Http\Controllers\Api\PromtController::class);
    
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
