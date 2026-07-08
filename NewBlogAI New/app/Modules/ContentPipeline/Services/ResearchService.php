<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\Contracts\ResearchServiceInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
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
            $queries  = $this->generateNewsQueriesForCategory($categorySubject, $category);

            $context->addResearchData('queries', $queries);
            $context->addResearchData('researched_at', now()->toIso8601String());

            Log::info('ResearchService: News research queries prepared successfully.', [
                'category'        => $category,
                'category_subject' => $categorySubject,
                'queries'         => $queries,
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
     * Generate structured news search queries based on category and subject.
     */
    protected function generateNewsQueriesForCategory(string $subject, string $category): array
    {
        $today = now()->format('Y-m-d');

        // Base news query templates applicable to all categories
        $baseTemplates = [
            '"' . $subject . '" breaking news ' . $today,
            '"' . $subject . '" latest updates today',
            'top ' . $subject . ' headlines',
        ];

        // Category-specific news query extensions
        $categoryTemplates = match (strtolower($category)) {
            'global'        => [
                'world news highlights today',
                'international breaking stories today',
                'global events ' . date('Y'),
            ],
            'trending'      => [
                'most shared trending news today',
                'viral news stories right now',
                'top trending stories ' . $today,
            ],
            'local'         => [
                'local community news today',
                'regional news updates ' . $today,
                'city council local events today',
            ],
            'technology'    => [
                'tech industry news and product launches today',
                'AI and software developments ' . date('Y'),
                'cybersecurity alerts and digital news today',
            ],
            'business'      => [
                'stock market and finance news today',
                'corporate earnings and economic data ' . $today,
                'startup and investment news today',
            ],
            'politics'      => [
                'government policy and political news today',
                'election and legislative updates ' . $today,
                'geopolitical developments and diplomacy news',
            ],
            'sports'        => [
                'sports scores and match results today',
                'athlete and team news ' . $today,
                'sports transfers and breaking sports news',
            ],
            'health'        => [
                'health and medical research news today',
                'public health alerts and wellness updates ' . $today,
                'new medical treatments and drug approvals today',
            ],
            'science'       => [
                'scientific research discoveries today',
                'space exploration and environment news ' . $today,
                'peer-reviewed study findings in the news',
            ],
            'entertainment' => [
                'celebrity and entertainment industry news today',
                'film, music, and TV releases ' . $today,
                'arts and culture events today',
            ],
            default => [
                'latest news headlines today',
                'top stories breaking news ' . $today,
            ],
        };

        return array_merge($baseTemplates, $categoryTemplates);
    }
}
