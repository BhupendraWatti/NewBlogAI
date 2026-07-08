<?php

namespace App\Modules\Analytics\Services;

use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\TopicManager\Services\CoverageService;
use Carbon\Carbon;

class AnalyticsService
{
    protected $coverageService;

    /**
     * Create a new service instance.
     */
    public function __construct(CoverageService $coverageService)
    {
        $this->coverageService = $coverageService;
    }

    /**
     * Generates count of generated articles per day for the last X days.
     *
     * @param int $siteId
     * @param int $days
     * @return array
     */
    public function getDailyGenerationStats(int $siteId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $items = GeneratedContent::where('site_id', $siteId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('created_at')
            ->get();

        $grouped = $items->groupBy(function ($item) {
            return Carbon::parse($item->created_at)->format('Y-m-d');
        })->map->count();

        $stats = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dateStr = Carbon::now()->subDays($i)->format('Y-m-d');
            $stats[$dateStr] = $grouped[$dateStr] ?? 0;
        }

        return $stats;
    }

    /**
     * Generates count of generated articles per month for the last X months.
     *
     * @param int $siteId
     * @param int $months
     * @return array
     */
    public function getMonthlyGenerationStats(int $siteId, int $months = 12): array
    {
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $items = GeneratedContent::where('site_id', $siteId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('created_at')
            ->get();

        $grouped = $items->groupBy(function ($item) {
            return Carbon::parse($item->created_at)->format('Y-m');
        })->map->count();

        $stats = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStr = Carbon::now()->subMonths($i)->format('Y-m');
            $stats[$monthStr] = $grouped[$monthStr] ?? 0;
        }

        return $stats;
    }

    /**
     * Aggregates prompt, completion, and total tokens used by the site over the specified period.
     *
     * @param int $siteId
     * @param int $days
     * @return array
     */
    public function getTokenUsageStats(int $siteId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $totals = AIRequestLog::where('site_id', $siteId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('SUM(prompt_tokens) as prompt, SUM(completion_tokens) as completion, SUM(total_tokens) as total')
            ->first();

        return [
            'prompt_tokens' => (int) ($totals->prompt ?? 0),
            'completion_tokens' => (int) ($totals->completion ?? 0),
            'total_tokens' => (int) ($totals->total ?? 0),
        ];
    }

    /**
     * Returns cumulative estimated AI costs per day.
     *
     * @param int $siteId
     * @param int $days
     * @return array
     */
    public function getCostEstimationStats(int $siteId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $items = AIRequestLog::where('site_id', $siteId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('created_at', 'estimated_cost')
            ->get();

        $grouped = $items->groupBy(function ($item) {
            return Carbon::parse($item->created_at)->format('Y-m-d');
        })->map(function ($group) {
            return $group->sum('estimated_cost');
        });

        $stats = [];
        $cumulative = 0.0;
        for ($i = $days - 1; $i >= 0; $i--) {
            $dateStr = Carbon::now()->subDays($i)->format('Y-m-d');
            $dayCost = $grouped[$dateStr] ?? 0.0;
            $cumulative += $dayCost;
            $stats[$dateStr] = (float) $cumulative;
        }

        return $stats;
    }

    /**
     * Computes success vs failure rates of pipeline runs and publishing attempts.
     *
     * @param int $siteId
     * @return array
     */
    public function getSuccessRateStats(int $siteId): array
    {
        $pipelineRuns = PipelineRun::whereHas('pipeline', function ($query) use ($siteId) {
            $query->where('site_id', $siteId);
        })->get();

        $pipelineSuccess = $pipelineRuns->where('status', 'completed')->count();
        $pipelineFailed = $pipelineRuns->where('status', 'failed')->count();

        $publishingLogs = PublishingLog::where('site_id', $siteId)->get();
        $publishingSuccess = $publishingLogs->where('status', 'completed')->count();
        $publishingFailed = $publishingLogs->where('status', 'failed')->count();

        return [
            'success' => $pipelineSuccess + $publishingSuccess,
            'failed' => $pipelineFailed + $publishingFailed,
            'pipeline' => [
                'success' => $pipelineSuccess,
                'failed' => $pipelineFailed,
            ],
            'publishing' => [
                'success' => $publishingSuccess,
                'failed' => $publishingFailed,
            ],
        ];
    }

    /**
     * Aggregates common error messages and count of failures from publishing_logs and pipeline_runs.
     *
     * @param int $siteId
     * @return array
     */
    public function getPublishFailures(int $siteId): array
    {
        $pipelineFailures = PipelineRun::whereHas('pipeline', function ($query) use ($siteId) {
            $query->where('site_id', $siteId);
        })
        ->where('status', 'failed')
        ->whereNotNull('error_message')
        ->where('error_message', '<>', '')
        ->pluck('error_message')
        ->toArray();

        $publishingFailures = PublishingLog::where('site_id', $siteId)
            ->where('status', 'failed')
            ->whereNotNull('error_message')
            ->where('error_message', '<>', '')
            ->pluck('error_message')
            ->toArray();

        $allFailures = array_merge($pipelineFailures, $publishingFailures);
        $counts = array_count_values($allFailures);
        arsort($counts);

        $result = [];
        foreach ($counts as $msg => $count) {
            $result[] = [
                'error' => $msg,
                'count' => $count,
            ];
        }

        return $result;
    }

    /**
     * Merges with CoverageService to return count and percentages of categories classified as fresh, stale, empty, and trending.
     *
     * @param int $siteId
     * @return array
     */
    public function getCategoryCoverageStats(int $siteId): array
    {
        $pipelineCategories = ContentPipeline::where('site_id', $siteId)
            ->pluck('news_category')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $generatedCategories = GeneratedContent::where('generated_contents.site_id', $siteId)
            ->join('content_pipelines', 'generated_contents.pipeline_id', '=', 'content_pipelines.id')
            ->pluck('content_pipelines.news_category')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $categories = array_unique(array_merge($pipelineCategories, $generatedCategories));

        $counts = [
            'fresh' => 0,
            'stale' => 0,
            'empty' => 0,
            'trending' => 0,
        ];

        foreach ($categories as $category) {
            $status = $this->coverageService->getCategoryStatus($siteId, $category);
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        $total = count($categories);
        $percentages = [
            'fresh' => $total > 0 ? round(($counts['fresh'] / $total) * 100, 2) : 0.0,
            'stale' => $total > 0 ? round(($counts['stale'] / $total) * 100, 2) : 0.0,
            'empty' => $total > 0 ? round(($counts['empty'] / $total) * 100, 2) : 0.0,
            'trending' => $total > 0 ? round(($counts['trending'] / $total) * 100, 2) : 0.0,
        ];

        return [
            'counts' => $counts,
            'percentages' => $percentages,
            'total' => $total,
        ];
    }

    /**
     * Returns breakdown of usage grouped by AI provider and model (e.g., OpenAI, Anthropic, Gemini) with cost and token counts.
     *
     * @param int $siteId
     * @return array
     */
    public function getProviderUsageStats(int $siteId): array
    {
        $usage = AIRequestLog::where('site_id', $siteId)
            ->selectRaw('provider, model, COUNT(*) as request_count, SUM(prompt_tokens) as prompt_tokens, SUM(completion_tokens) as completion_tokens, SUM(total_tokens) as total_tokens, SUM(estimated_cost) as estimated_cost')
            ->groupBy('provider', 'model')
            ->get();

        return $usage->map(function ($item) {
            return [
                'provider' => $item->provider,
                'model' => $item->model,
                'request_count' => (int) $item->request_count,
                'prompt_tokens' => (int) $item->prompt_tokens,
                'completion_tokens' => (int) $item->completion_tokens,
                'total_tokens' => (int) $item->total_tokens,
                'estimated_cost' => (float) $item->estimated_cost,
            ];
        })->toArray();
    }
}
