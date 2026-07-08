<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\Contracts\ResearchServiceInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use Illuminate\Support\Facades\Log;

class ResearchService implements ResearchServiceInterface
{
    /**
     * Process the current stage of the content pipeline.
     * Prepares search-oriented queries based on the topic.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        try {
            Log::info('ResearchService: Preparing research queries.');

            $topicName = $context->resolvedTopic;
            if (empty($topicName)) {
                throw new \RuntimeException('Topic name has not been resolved yet.');
            }

            $category = $context->metadata['resolved_topic_category'] ?? 'General';
            $queries = $this->generateQueriesForTopic($topicName, $category);

            $context->addResearchData('queries', $queries);
            $context->addResearchData('researched_at', now()->toIso8601String());

            Log::info('ResearchService: Prepared queries successfully.', [
                'topic' => $topicName,
                'category' => $category,
                'queries' => $queries
            ]);
        } catch (\Exception $e) {
            Log::error('ResearchService failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            $context->addError('research_service', $e->getMessage());
        }

        return $context;
    }

    /**
     * Generate structured queries based on topic and category.
     */
    protected function generateQueriesForTopic(string $topicName, string $category): array
    {
        $categoryLower = strtolower($category);

        // Define search-oriented templates based on category
        $templates = match ($categoryLower) {
            'tech', 'technology', 'software' => [
                '"{topic}" latest updates and features',
                '"{topic}" tutorial and best practices',
                '"{topic}" architecture and design patterns',
                'future of "{topic}" and industry trends',
            ],
            'finance', 'business', 'economy' => [
                '"{topic}" market trends and statistics',
                'how to invest in "{topic}"',
                '"{topic}" analysis and reports 2026',
                'impact of "{topic}" on global business',
            ],
            'health', 'medical', 'wellness' => [
                '"{topic}" benefits and side effects',
                'latest research on "{topic}"',
                'how to improve "{topic}"',
                '"{topic}" guide for beginners',
            ],
            default => [
                '"{topic}" overview and definitions',
                'latest news about "{topic}"',
                '"{topic}" guide and tutorial',
                'key challenges and opportunities in "{topic}"',
            ],
        };

        $queries = [];
        foreach ($templates as $template) {
            $queries[] = str_replace('{topic}', $topicName, $template);
        }

        return $queries;
    }
}
