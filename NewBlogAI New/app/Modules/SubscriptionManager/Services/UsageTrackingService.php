<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionManager\Services;

use App\Modules\ContentGeneration\Models\AIRequestLog;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\MediaManager\Models\MediaItem;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Support\Carbon;

class UsageTrackingService
{
    public function __construct(
        protected EntitlementService $entitlements
    ) {}

    /**
     * Log and track usage data for a given website workspace (site).
     */
    public function recordUsage(Site $site, array $metrics): void
    {
        $subscription = $this->entitlements->subscriptionForSite($site);

        AIRequestLog::create([
            'customer_id' => $site->customer_id,
            'subscription_id' => $subscription?->id,
            'site_id' => $site->id,
            'provider' => $metrics['provider'] ?? 'unknown',
            'model' => $metrics['model'] ?? 'unknown',
            'prompt_id' => $metrics['prompt_id'] ?? null,
            'topic_id' => $metrics['topic_id'] ?? null,
            'execution_time_ms' => $metrics['execution_time_ms'] ?? 0,
            'prompt_tokens' => $metrics['prompt_tokens'] ?? null,
            'completion_tokens' => $metrics['completion_tokens'] ?? null,
            'total_tokens' => $metrics['total_tokens'] ?? null,
            'estimated_cost' => $metrics['estimated_cost'] ?? 0.0,
            'status' => $metrics['status'] ?? 'success',
            'response_metadata' => array_merge([
                'image_generation_count' => $metrics['image_generation_count'] ?? 0,
                'video_generation_count' => $metrics['video_generation_count'] ?? 0,
                'prompt_count' => $metrics['prompt_count'] ?? 0,
                'generated_articles' => $metrics['generated_articles'] ?? 0,
                'published_articles' => $metrics['published_articles'] ?? 0,
            ], $metrics['response_metadata'] ?? []),
            'error_log' => $metrics['error_log'] ?? null,
        ]);
    }

    /**
     * Get dynamic workspace usage statistics for a website workspace (site).
     */
    public function getUsageStats(Site $site, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        // Query AIRequestLogs for tokens, estimated cost, and provider details
        $aiLogs = AIRequestLog::where('site_id', $site->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $promptTokens = (int) $aiLogs->sum('prompt_tokens');
        $completionTokens = (int) $aiLogs->sum('completion_tokens');
        $totalTokens = (int) $aiLogs->sum('total_tokens');
        $estimatedCost = (float) $aiLogs->sum('estimated_cost');

        // Extract metadata counts from AIRequestLogs (e.g. image, video, prompt counts)
        $metaImageCount = 0;
        $metaVideoCount = 0;
        $metaPromptCount = 0;
        foreach ($aiLogs as $log) {
            $meta = $log->response_metadata ?? [];
            $metaImageCount += (int) ($meta['image_generation_count'] ?? 0);
            $metaVideoCount += (int) ($meta['video_generation_count'] ?? 0);
            $metaPromptCount += (int) ($meta['prompt_count'] ?? 0);
        }

        // Aggregate AI provider details
        $providerMetrics = [];
        foreach ($aiLogs->groupBy('provider') as $provider => $logs) {
            $providerMetrics[$provider] = [
                'count' => $logs->count(),
                'total_tokens' => (int) $logs->sum('total_tokens'),
                'estimated_cost' => (float) $logs->sum('estimated_cost'),
            ];
        }

        // Query generated content count
        $generatedArticlesCount = GeneratedContent::where('site_id', $site->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Query published articles count
        $publishedArticlesCount = PublishingLog::where('site_id', $site->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Query actual generated MediaItems (images/videos)
        $contentIds = GeneratedContent::where('site_id', $site->id)->pluck('id');
        $mediaItems = MediaItem::whereIn('generated_content_id', $contentIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $actualImages = $mediaItems->filter(fn($item) => !str_contains($item->filename ?? '', '.mp4'))->count();
        $actualVideos = $mediaItems->filter(fn($item) => str_contains($item->filename ?? '', '.mp4'))->count();

        // Prompt count is the number of unique prompt templates used
        $uniquePromptsUsed = $aiLogs->whereNotNull('prompt_id')->unique('prompt_id')->count();

        return [
            'generated_articles' => $generatedArticlesCount,
            'published_articles' => $publishedArticlesCount,
            'image_generations' => $actualImages + $metaImageCount,
            'video_generations' => $actualVideos + $metaVideoCount,
            'prompt_count' => max($uniquePromptsUsed, $metaPromptCount),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost' => $estimatedCost,
            'provider_metrics' => $providerMetrics,
        ];
    }
}
