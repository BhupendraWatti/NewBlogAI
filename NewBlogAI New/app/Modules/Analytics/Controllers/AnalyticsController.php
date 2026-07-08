<?php

namespace App\Modules\Analytics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\AnalyticsService;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Helper to assert that user has access to the specified site's analytics.
     */
    protected function assertSiteAccess(int $siteId): void
    {
        $site = Site::findOrFail($siteId);
        $user = Auth::user();

        // Standard tenant isolation check
        if ($user->role !== 1 && $site->customer_id !== $user->customer_id) {
            abort(403, 'Unauthorized access to site analytics.');
        }
    }

    /**
     * Get category coverage stats.
     */
    public function coverage(int $siteId): JsonResponse
    {
        $this->assertSiteAccess($siteId);
        return response()->json($this->analyticsService->getCategoryCoverageStats($siteId));
    }

    /**
     * Get daily article generation stats.
     */
    public function daily(int $siteId): JsonResponse
    {
        $this->assertSiteAccess($siteId);
        return response()->json($this->analyticsService->getDailyGenerationStats($siteId));
    }

    /**
     * Get monthly article generation stats.
     */
    public function monthly(int $siteId): JsonResponse
    {
        $this->assertSiteAccess($siteId);
        return response()->json($this->analyticsService->getMonthlyGenerationStats($siteId));
    }

    /**
     * Get token usage stats.
     */
    public function tokens(int $siteId): JsonResponse
    {
        $this->assertSiteAccess($siteId);
        return response()->json($this->analyticsService->getTokenUsageStats($siteId));
    }

    /**
     * Get cost estimation stats.
     */
    public function costs(int $siteId): JsonResponse
    {
        $this->assertSiteAccess($siteId);
        return response()->json($this->analyticsService->getCostEstimationStats($siteId));
    }

    /**
     * Get success rate stats.
     */
    public function successRate(int $siteId): JsonResponse
    {
        $this->assertSiteAccess($siteId);
        return response()->json($this->analyticsService->getSuccessRateStats($siteId));
    }

    /**
     * Get publish failures list.
     */
    public function failures(int $siteId): JsonResponse
    {
        $this->assertSiteAccess($siteId);
        return response()->json($this->analyticsService->getPublishFailures($siteId));
    }

    /**
     * Get provider usage stats.
     */
    public function providers(int $siteId): JsonResponse
    {
        $this->assertSiteAccess($siteId);
        return response()->json($this->analyticsService->getProviderUsageStats($siteId));
    }
}
