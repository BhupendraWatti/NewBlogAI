<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\Operations\Models\AuditLog;
use App\Modules\Operations\Models\JobLog;
use App\Modules\Operations\Services\AnalyticsService;
use App\Modules\Operations\Services\SystemHealthService;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class OperationsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected SystemHealthService $healthService,
        protected EntitlementService $entitlements,
    ) {}

    /**
     * Get system diagnostic and health checks.
     */
    public function health(): JsonResponse
    {
        return response()->json($this->healthService->getSystemHealth());
    }

    /**
     * Get AI request performance and token usage stats.
     */
    public function aiStats(): JsonResponse
    {
        $customerId = Auth::user()?->customer_id;
        if ($customerId) {
            $this->entitlements->assertFeatureEnabled(Customer::findOrFail($customerId), 'analytics');
        }

        return response()->json($this->analyticsService->getAIStatistics($customerId));
    }

    /**
     * Get generated/published content metrics.
     */
    public function contentStats(): JsonResponse
    {
        $customerId = Auth::user()?->customer_id;
        if ($customerId) {
            $this->entitlements->assertFeatureEnabled(Customer::findOrFail($customerId), 'analytics');
        }

        return response()->json($this->analyticsService->getContentStatistics($customerId));
    }

    /**
     * Display listing of audited configuration actions.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('user')->latest('id');

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        $logs = $query->paginate($request->input('limit', 15));

        return response()->json($logs);
    }

    /**
     * Display listing of monitored background queue jobs.
     */
    public function jobLogs(Request $request): JsonResponse
    {
        $query = JobLog::latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->paginate($request->input('limit', 15));

        return response()->json($logs);
    }

    /**
     * Manually trigger failed job retry.
     */
    public function retryJob(string $id): JsonResponse
    {
        // Find the monitored job log
        $job = JobLog::findOrFail($id);

        if ($job->status !== 'failed') {
            return response()->json(['message' => 'Job has not failed.'], 422);
        }

        // Trigger Laravel standard command to retry failed job
        Artisan::call('queue:retry', ['id' => $job->job_id]);

        // Log manual audit action
        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => 'queue_job_retried',
            'new_values' => ['job_id' => $job->job_id, 'name' => $job->name],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'message' => 'Job retry command triggered successfully.',
        ]);
    }
}
