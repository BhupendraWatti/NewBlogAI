<?php

namespace App\Modules\TopicManager\Services;

use App\Modules\TopicManager\Models\Topic;
use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use Carbon\Carbon;

class CoverageService
{
    /**
     * Get the freshness status of a category for a specific site.
     *
     * @param int $siteId
     * @param string $category
     * @return string
     */
    public function getCategoryStatus(int $siteId, string $category): string
    {
        // 1. Check for empty
        $contentsCount = GeneratedContent::where('site_id', $siteId)
            ->whereHas('topic', function ($query) use ($category) {
                $query->where('category', $category);
            })
            ->count();

        if ($contentsCount === 0) {
            return 'empty';
        }

        // 2. Check for trending
        // trending: has high volume of recent generation (e.g., 3+ articles in the last 2 days) or marked as trending in metadata.
        $recentCount = GeneratedContent::where('site_id', $siteId)
            ->whereHas('topic', function ($query) use ($category) {
                $query->where('category', $category);
            })
            ->where('created_at', '>=', Carbon::now()->subDays(2))
            ->count();

        $hasTrendingMetadata = GeneratedContent::where('site_id', $siteId)
            ->whereHas('topic', function ($query) use ($category) {
                $query->where('category', $category);
            })
            ->where(function ($q) {
                $q->whereJsonContains('metadata->trending', true)
                  ->orWhere('metadata->trending', 'true')
                  ->orWhere('metadata->trending', 1);
            })
            ->exists();

        if ($recentCount >= 3 || $hasTrendingMetadata) {
            return 'trending';
        }

        // 3. Check for fresh
        // fresh: has generated content in the category within the last 7 days.
        $hasRecentContent = GeneratedContent::where('site_id', $siteId)
            ->whereHas('topic', function ($query) use ($category) {
                $query->where('category', $category);
            })
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->exists();

        if ($hasRecentContent) {
            return 'fresh';
        }

        // 4. Otherwise, stale (has content, but none within the last 7 days)
        return 'stale';
    }

    /**
     * Get recommendations for categories needing new articles (prioritizing Empty, then Stale).
     *
     * @param int $siteId
     * @return array
     */
    public function getRecommendations(int $siteId): array
    {
        // Get unique categories associated with the site from content pipelines and generated contents
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

        $emptyRecommendations = [];
        $staleRecommendations = [];

        foreach ($categories as $category) {
            $status = $this->getCategoryStatus($siteId, $category);
            if ($status === 'empty') {
                $emptyRecommendations[] = [
                    'category' => $category,
                    'status' => 'empty',
                ];
            } elseif ($status === 'stale') {
                $staleRecommendations[] = [
                    'category' => $category,
                    'status' => 'stale',
                ];
            }
        }

        return array_merge($emptyRecommendations, $staleRecommendations);
    }
}
