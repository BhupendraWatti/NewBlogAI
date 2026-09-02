<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\Contracts\ResearchServiceInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\SystemSettings\Models\MasterOption;
use Illuminate\Support\Facades\Log;

class ResearchService implements ResearchServiceInterface
{
    /**
     * Process the research stage of the news content pipeline.
     * Prepares search-oriented news queries based on the resolved category subject.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        try {
            Log::info('ResearchService: Preparing news research queries.');

            $categorySubject = $context->resolvedTopic;
            if (empty($categorySubject)) {
                throw new \RuntimeException('Category subject has not been resolved yet (resolvedTopic is empty).');
            }

            $category = $context->metadata['news_category'] ?? 'global';

            // Newsroom workflow: when an employee-selected candidate anchors
            // this run, research the exact event instead of generic category
            // headlines, and snapshot the candidate into the research data so
            // downstream stages audit against the authoritative event.
            $selectedNews = $context->metadata['selected_news'] ?? null;
            if (is_array($selectedNews) && ! empty($selectedNews['title'])) {
                $queries = $this->generateQueriesForSelectedNews($selectedNews, $category);
                $context->addResearchData('selected_news', $selectedNews);
            } else {
                $queries = $this->generateNewsQueriesForCategory($categorySubject, $category);
            }

            $context->addResearchData('queries', $queries);
            $context->addResearchData('researched_at', now()->toIso8601String());

            Log::info('ResearchService: News research queries prepared successfully.', [
                'category' => $category,
                'category_subject' => $categorySubject,
                'queries' => $queries,
            ]);
        } catch (\Exception $e) {
            Log::error('ResearchService failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);
            $context->addError('research_service', $e->getMessage());
        }

        return $context;
    }

    /**
     * Generate research queries anchored to an employee-selected news
     * candidate (exact event verification instead of generic headlines).
     */
    protected function generateQueriesForSelectedNews(array $selectedNews, string $category): array
    {
        $headline = trim((string) $selectedNews['title']);
        $keywords = array_filter(array_map('strval', (array) ($selectedNews['keywords'] ?? [])));
        $today = now()->format('Y-m-d');

        $queries = [
            '"'.$headline.'"',
            $headline.' latest updates '.$today,
            $headline.' official statement source verification',
        ];

        if (! empty($keywords)) {
            $queries[] = implode(' ', array_slice($keywords, 0, 4)).' '.$category.' news';
        }

        return $queries;
    }

    /**
     * Generate structured news search queries based on category and subject.
     */
    protected function generateNewsQueriesForCategory(string $subject, string $category): array
    {
        $today = now()->format('Y-m-d');

        // Base news query templates applicable to all categories
        $baseTemplates = [
            '"'.$subject.'" breaking news '.$today,
            '"'.$subject.'" latest updates today',
            'top '.$subject.' headlines',
        ];

        // Check if MasterOption metadata defines custom search query templates for this topic
        try {
            $catLower = strtolower(trim($category));
            $option = MasterOption::ofType('topic')
                ->where(function ($q) use ($catLower) {
                    $q->whereRaw('LOWER(name) = ?', [$catLower])
                        ->orWhere('code', $catLower);
                })
                ->first();

            if ($option && ! empty($option->metadata['queries']) && is_array($option->metadata['queries'])) {
                return array_values(array_unique(array_merge($baseTemplates, $option->metadata['queries'])));
            }
        } catch (\Throwable) {
            // Fall through safely
        }

        // Administrators can specialize these through MasterOption metadata.
        // The fallback stays category-agnostic so new SaaS topics need no code change.
        $categoryTemplates = [
            "latest {$category} updates and developments {$today}",
            "top {$category} news stories today",
            "breaking {$category} headlines ".now()->year,
        ];

        return array_merge($baseTemplates, $categoryTemplates);
    }
}
