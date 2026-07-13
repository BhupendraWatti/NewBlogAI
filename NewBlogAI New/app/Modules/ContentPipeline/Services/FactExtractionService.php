<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\Contracts\FactExtractorInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use Illuminate\Support\Facades\Log;

class FactExtractionService implements FactExtractorInterface
{
    /**
     * Process the current stage of the content pipeline.
     * Extracts key entities and facts (People, Organizations, Locations, Dates, Events, Keywords) from sources.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        try {
            Log::info('FactExtractionService: Starting fact and entity extraction.');

            $sources = $context->sources;
            $topic = $context->resolvedTopic;

            if (empty($topic)) {
                throw new \RuntimeException('Topic has not been resolved. Cannot extract facts.');
            }

            // Standardize container for extracted facts
            $facts = [
                'people' => [],
                'organizations' => [],
                'locations' => [],
                'dates' => [],
                'events' => [],
                'keywords' => []
            ];

            // Extract keywords from topic itself
            $facts['keywords'][] = $topic;
            $topicWords = explode(' ', $topic);
            foreach ($topicWords as $word) {
                if (strlen($word) > 3) {
                    $facts['keywords'][] = trim($word, '.,()[]');
                }
            }

            // Process each source to extract facts
            foreach ($sources as $source) {
                // Extract author as People if available
                if (!empty($source['metadata']['author'])) {
                    $author = $source['metadata']['author'];
                    if ($author !== 'Community Contributors' && !in_array($author, $facts['people'], true)) {
                        $facts['people'][] = $author;
                    }
                }

                // Extract publisher as Organization
                if (!empty($source['metadata']['publisher'])) {
                    $pub = $source['metadata']['publisher'];
                    if (!in_array($pub, $facts['organizations'], true)) {
                        $facts['organizations'][] = $pub;
                    }
                }

                // Extract published date
                if (!empty($source['metadata']['published_date'])) {
                    $date = $source['metadata']['published_date'];
                    if (!in_array($date, $facts['dates'], true)) {
                        $facts['dates'][] = $date;
                    }
                }

                // Analyze title and snippet for keywords
                $combinedText = ($source['title'] ?? '') . ' ' . ($source['snippet'] ?? '');
                $words = str_word_count(strtolower($combinedText), 1);
                foreach ($words as $word) {
                    if (strlen($word) > 5 && !in_array($word, ['article', 'covers', 'essential', 'concepts', 'highlights', 'findings', 'milestones'], true)) {
                        if (!in_array($word, $facts['keywords'], true) && count($facts['keywords']) < 15) {
                            $facts['keywords'][] = $word;
                        }
                    }
                }
            }

            // De-duplicate lists and clean up
            foreach ($facts as $key => $values) {
                $facts[$key] = array_values(array_unique(array_filter($values)));
            }

            // Save facts to metadata and researchData
            $context->metadata['extracted_facts'] = $facts;
            $context->addResearchData('extracted_facts', $facts);

            Log::info('FactExtractionService: Fact extraction completed.', [
                'extracted_counts' => [
                    'people' => count($facts['people']),
                    'organizations' => count($facts['organizations']),
                    'locations' => count($facts['locations']),
                    'dates' => count($facts['dates']),
                    'events' => count($facts['events']),
                    'keywords' => count($facts['keywords']),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('FactExtractionService failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            $context->addError('fact_extractor', $e->getMessage());
        }

        return $context;
    }

}
