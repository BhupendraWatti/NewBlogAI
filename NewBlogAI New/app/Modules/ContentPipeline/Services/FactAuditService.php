<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\Contracts\FactAuditorInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\DTOs\SourceDTO;
use Illuminate\Support\Facades\Log;

class FactAuditService implements FactAuditorInterface
{
    /**
     * Process the current stage of the content pipeline.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        try {
            Log::info('FactAuditService: Starting fact audit and verification.');

            $content = $context->generatedContent;
            if (empty($content)) {
                Log::warning('FactAuditService: No generated content to audit.');
                $context->metadata['fact_audit'] = [
                    'fact_score' => 100,
                    'confidence_score' => 0.0,
                    'supported_claims' => [],
                    'unsupported_claims' => [],
                    'references' => [],
                ];
                return $context;
            }

            // 1. Extract claims dynamically from content
            $claims = $this->extractClaims($content);

            $supportedClaims = [];
            $unsupportedClaims = [];
            $usedReferences = [];

            // 2. Verify each claim against the collected sources
            foreach ($claims as $claim) {
                $matchingSources = [];
                
                foreach ($context->sources as $source) {
                    $sourceObj = $source instanceof SourceDTO ? $source : SourceDTO::fromArray($source);
                    
                    $matchScore = $this->calculateMatchScore($claim, $sourceObj);
                    if ($matchScore > 0.25) { // Match threshold
                        $matchingSources[] = [
                            'title' => $sourceObj->title,
                            'url' => $sourceObj->url,
                            'relevance_score' => $sourceObj->relevanceScore,
                            'match_score' => $matchScore,
                        ];
                        
                        $usedReferences[$sourceObj->url] = [
                            'title' => $sourceObj->title,
                            'url' => $sourceObj->url,
                        ];
                    }
                }

                if (!empty($matchingSources)) {
                    // Sort matching sources by match_score descending
                    usort($matchingSources, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
                    
                    $supportedClaims[] = [
                        'claim' => $claim,
                        'sources' => $matchingSources,
                    ];
                } else {
                    $unsupportedClaims[] = $claim;
                }
            }

            // 3. Calculate dynamic scores
            $totalClaims = count($claims);
            if ($totalClaims > 0) {
                $factScore = (int) round((count($supportedClaims) / $totalClaims) * 100);
                
                // Confidence score based on relevance of sources and match scores
                $totalConfidence = 0.0;
                foreach ($supportedClaims as $sc) {
                    $maxConfidence = 0.0;
                    foreach ($sc['sources'] as $src) {
                        // Blend relevance score (0.0 to 1.0) and match score (0.0 to 1.0)
                        $scoreBlend = ($src['relevance_score'] + $src['match_score']) / 2.0;
                        if ($scoreBlend > $maxConfidence) {
                            $maxConfidence = $scoreBlend;
                        }
                    }
                    $totalConfidence += $maxConfidence;
                }
                $confidenceScore = round($totalConfidence / $totalClaims, 2);
            } else {
                $factScore = 100;
                $confidenceScore = 1.0;
            }

            // Ensure scores are in valid bounds
            $factScore = max(0, min(100, $factScore));
            $confidenceScore = max(0.0, min(1.0, $confidenceScore));

            $factAuditResult = [
                'fact_score' => $factScore,
                'confidence_score' => $confidenceScore,
                'supported_claims' => $supportedClaims,
                'unsupported_claims' => $unsupportedClaims,
                'references' => array_values($usedReferences),
            ];

            $context->metadata['fact_audit'] = $factAuditResult;

            Log::info('FactAuditService: Fact audit completed successfully.', [
                'fact_score' => $factScore,
                'confidence_score' => $confidenceScore,
                'supported_claims_count' => count($supportedClaims),
                'unsupported_claims_count' => count($unsupportedClaims),
            ]);

        } catch (\Exception $e) {
            Log::error('FactAuditService failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            $context->addError('fact_auditor', $e->getMessage());
        }

        return $context;
    }

    /**
     * Extract claims dynamically from content using text structure, syntax, and keywords.
     */
    protected function extractClaims(string $content): array
    {
        // Remove markdown headings, bold formatting, etc.
        $cleanText = preg_replace('/^[#\-\*\s]+/m', '', $content);
        $cleanText = preg_replace('/\*\*([^*]+)\*\*/', '$1', $cleanText);

        // Split into sentences using common punctuation
        $sentences = preg_split('/(?<=[.?!])\s+/', $cleanText);
        $claims = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) < 15) {
                continue;
            }

            // Check if sentence looks like a factual claim (contains numbers, years, proper nouns, or factual verbs)
            $hasNumber = (bool) preg_match('/\b\d+(?:[.,]\d+)?%?\b/', $sentence);
            $hasFactualVerbs = (bool) preg_match('/\b(is|are|was|were|developed|created|founded|released|launched|announced|increased|decreased|achieved|reaches|contains)\b/i', $sentence);
            $hasCapitalizedWords = (bool) preg_match('/[A-Z][a-z]+/', $sentence);

            if ($hasNumber || ($hasFactualVerbs && $hasCapitalizedWords)) {
                $claims[] = $sentence;
            }
        }

        // Return a maximum of 10 claims to keep auditing performance efficient
        return array_slice(array_unique($claims), 0, 10);
    }

    /**
     * Calculate overlap/similarity score between a claim and a source.
     */
    protected function calculateMatchScore(string $claim, SourceDTO $source): float
    {
        $claimLower = strtolower($claim);
        $sourceText = strtolower(($source->title ?? '') . ' ' . ($source->snippet ?? ''));

        // Direct containment check (highly supportive)
        if (str_contains($sourceText, $claimLower)) {
            return 1.0;
        }

        // Keyword overlap calculation
        $claimWords = str_word_count($claimLower, 1);
        
        // Filter out short words and common stop words
        $stopWords = ['the', 'and', 'a', 'of', 'to', 'in', 'is', 'that', 'it', 'for', 'on', 'with', 'as', 'this', 'was', 'are', 'by', 'an', 'be', 'at'];
        $keywords = array_filter($claimWords, function ($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords, true);
        });

        if (empty($keywords)) {
            return 0.0;
        }

        $matchCount = 0;
        foreach ($keywords as $word) {
            if (str_contains($sourceText, $word)) {
                $matchCount++;
            }
        }

        // Return ratio of matching keywords
        return $matchCount / count($keywords);
    }
}
