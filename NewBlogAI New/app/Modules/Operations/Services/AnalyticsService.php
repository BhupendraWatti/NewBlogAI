<?php

namespace App\Modules\Operations\Services;

use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\Publishing\Models\PublishingLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get aggregate statistics for AI consumption.
     */
    public function getAIStatistics(): array
    {
        return Cache::remember('analytics_ai_stats', 300, function () {
            $aggregates = AIRequestLog::selectRaw('
                COUNT(id) as total_requests,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful_requests,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_requests,
                SUM(prompt_tokens) as total_prompt_tokens,
                SUM(completion_tokens) as total_completion_tokens,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost) as total_cost
            ')->first();

            // Provider breakdown
            $providers = AIRequestLog::select('provider', DB::raw('COUNT(id) as count'), DB::raw('SUM(estimated_cost) as cost'))
                ->groupBy('provider')
                ->get()
                ->toArray();

            return [
                'total_requests'      => (int) ($aggregates->total_requests ?? 0),
                'successful_requests' => (int) ($aggregates->successful_requests ?? 0),
                'failed_requests'     => (int) ($aggregates->failed_requests ?? 0),
                'total_prompt_tokens' => (int) ($aggregates->total_prompt_tokens ?? 0),
                'total_completion_tokens' => (int) ($aggregates->total_completion_tokens ?? 0),
                'total_tokens'        => (int) ($aggregates->total_tokens ?? 0),
                'total_cost'          => (float) ($aggregates->total_cost ?? 0.0),
                'providers'           => $providers,
            ];
        });
    }

    /**
     * Get statistics regarding generated content and publications.
     */
    public function getContentStatistics(): array
    {
        return Cache::remember('analytics_content_stats', 300, function () {
            $totalArticles = GeneratedContent::count();
            
            $statusBreakdown = GeneratedContent::select('status', DB::raw('COUNT(id) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray();

            $publishingSuccessRate = 0.0;
            $publishingTotal = PublishingLog::count();
            if ($publishingTotal > 0) {
                $completed = PublishingLog::where('status', 'completed')->count();
                $publishingSuccessRate = round(($completed / $publishingTotal) * 100, 2);
            }

            return [
                'total_articles'          => $totalArticles,
                'status_breakdown'        => $statusBreakdown,
                'total_publishing_runs'   => $publishingTotal,
                'publishing_success_rate' => $publishingSuccessRate,
            ];
        });
    }
}
