<?php

namespace App\Modules\TopicManager\Services;

use App\Modules\ContentGeneration\Models\GeneratedContent;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CoverageService
{
    /**
     * Get the freshness status of a news category for a specific site.
     *
     * Category-driven (ADR-003): content is matched through its pipeline's
     * news_category. The legacy topic relation no longer exists on
     * GeneratedContent and must not be queried.
     *
     * @param int $siteId
     * @param string $category
     * @return string one of: empty|trending|fresh|stale
     */
    public function getCategoryStatus(int $siteId, string $category): string
    {
        $category = strtolower(trim($category));

        // 1. Check for empty
        if (! $this->categoryContentQuery($siteId, $category)->exists()) {
            return 'empty';
        }

        // 2. Check for trending
        // trending: has high volume of recent generation (3+ articles in the
        // last 2 days) or marked as trending in metadata.
        $recentCount = $this->categoryContentQuery($siteId, $category)
            ->where('generated_contents.created_at', '>=', Carbon::now()->subDays(2))
            ->count();

        $hasTrendingMetadata = $this->categoryContentQuery($siteId, $category)
            ->where(function ($q) {
                $q->whereJsonContains('generated_contents.metadata->trending', true)
                    ->orWhere('generated_contents.metadata->trending', 'true')
                    ->orWhere('generated_contents.metadata->trending', 1);
            })
            ->exists();

        if ($recentCount >= 3 || $hasTrendingMetadata) {
            return 'trending';
        }

        // 3. Check for fresh
        // fresh: has generated content in the category within the last 7 days.
        $hasRecentContent = $this->categoryContentQuery($siteId, $category)
            ->where('generated_contents.created_at', '>=', Carbon::now()->subDays(7))
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

    /**
     * Base query for a site's generated content in a given news category.
     */
    protected function categoryContentQuery(int $siteId, string $category): Builder
    {
        return GeneratedContent::query()
            ->join('content_pipelines', 'generated_contents.pipeline_id', '=', 'content_pipelines.id')
            ->where('generated_contents.site_id', $siteId)
            ->whereRaw('LOWER(content_pipelines.news_category) = ?', [$category]);
    }
}
