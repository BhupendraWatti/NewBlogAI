<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\Contracts\FactAuditorInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\DTOs\SourceDTO;
use Illuminate\Support\Facades\Log;

class FactAuditService implements FactAuditorInterface
{
    public const MIN_FACT_SCORE = 70;

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
                    'fact_score'      => 100,
                    'confidence_score'=> 0.0,
                    'supported_claims'=> [],
                    'unsupported_claims' => [],
                    'references'      => [],
                    'w_h_validation'  => [
                        'who_what' => false,
                        'when'     => false,
                        'where'    => false,
                        'how_why'  => false,
                        'passed'   => false,
                    ],
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

            $wHValidation = $this->validateWAndHHeuristics($content);

            $factAuditResult = [
                'fact_score' => $factScore,
                'confidence_score' => $confidenceScore,
                'supported_claims' => $supportedClaims,
                'unsupported_claims' => $unsupportedClaims,
                'references' => array_values($usedReferences),
                'w_h_validation' => $wHValidation,
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
        $claimLower = mb_strtolower($claim, 'UTF-8');
        $sourceText = mb_strtolower(($source->title ?? '') . ' ' . ($source->snippet ?? ''), 'UTF-8');

        // Direct containment check (highly supportive)
        if (str_contains($sourceText, $claimLower)) {
            return 1.0;
        }

        // Keyword overlap calculation: Use Unicode-safe word matching
        $claimWords = array_filter(preg_split('/[^\p{L}\p{N}]+/u', $claimLower) ?: []);
        
        // Filter out short words and common stop words
        $stopWords = ['the', 'and', 'a', 'of', 'to', 'in', 'is', 'that', 'it', 'for', 'on', 'with', 'as', 'this', 'was', 'are', 'by', 'an', 'be', 'at'];
        $keywords = array_filter($claimWords, function ($word) use ($stopWords) {
            return mb_strlen($word) >= 3 && !in_array($word, $stopWords, true);
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

    /**
     * Heuristic verification check to see if the content covers the 5 Ws and H.
     * Searches for keywords, entities, and patterns representing Who/What, When, Where, Why, and How.
     */
    protected function validateWAndHHeuristics(string $content): array
    {
        $contentLower = mb_strtolower($content);

        // 1. When — year (19xx/20xx), or any named month/time-of-day indicator in English or Hindi
        $hasWhen = preg_match(
            '/(?:19|20)\d{2}|\b(?:today|yesterday|monday|tuesday|wednesday|thursday|friday|saturday|sunday|january|february|march|april|may|june|july|august|september|october|november|december|am|pm)\b/i',
            $content
        ) === 1
        || preg_match(
            '/\b(?:जनवरी|फ़रवरी|मार्च|अप्रैल|मई|जून|जुलाई|अगस्त|सितंबर|अक्टूबर|नवंबर|दिसंबर|बजे|वर्ष|साल|महीने|आज|कल|परसों)\b/u',
            $content
        ) === 1;

        // 2. Where — detect a place indicator generically:
        //    English: any capitalised word followed by known location suffixes (City/District/State/Village/Pradesh/Nagar),
        //    OR a word from the 18 largest Indian states/UTs appearing anywhere (case-insensitive),
        //    Hindi: location-indicator nouns (शहर, जिला, राज्य, स्थान, देश, इलाके, नगर, गाँव, ग्राम, मोहल्ला, तहसील, ज़िला)
        $hasWhere = preg_match(
            '/\b[A-Z][a-z]+(?: City| District| State| Pradesh| Nagar| Village| Town)?\b/u',
            $content
        ) === 1
        || preg_match(
            '/\b(?:india|pakistan|delhi|mumbai|kolkata|chennai|bengaluru|bangalore|hyderabad|pune|ahmedabad|surat|jaipur|lucknow|bhopal|indore|nagpur|patna|vadodara|agra|nashik|ujjain|madhya pradesh|maharashtra|gujarat|rajasthan|uttar pradesh|bihar|odisha|karnataka|kerala|tamil nadu|west bengal|assam|punjab|haryana|himachal|goa|jharkhand|uttarakhand|chhattisgarh|telangana|andhra pradesh)\b/i',
            $contentLower
        ) === 1
        || preg_match(
            '/\b(?:शहर|जिला|ज़िला|राज्य|स्थान|देश|इलाके|नगर|गाँव|ग्राम|मोहल्ला|तहसील|भारत|प्रदेश|उज्जैन|दिल्ली|मुंबई|हैदराबाद|बेंगलुरु|पुणे|अहमदाबाद|जयपुर|लखनऊ|भोपाल|इंदौर|नागपुर|पटना|सूरत|आगरा|नासिक|कोलकाता|चेन्नई)\b/u',
            $content
        ) === 1;

        // 3. Who/What — English: any capitalized proper noun (≥ 2 chars after first cap);
        //    Hindi: known role/organization indicator nouns
        $hasWho = preg_match('/\b[A-Z][a-zA-Z]{1,}\b/u', $content) === 1
            || preg_match(
                '/\b(?:पुलिस|प्रशासन|मुख्यमंत्री|प्रधानमंत्री|सरकार|कंपनी|अधिकारी|लोग|बचाव दल|नेता|मंत्री|विभाग|संगठन|संस्था)\b/u',
                $content
            ) === 1;

        // 4. How/Why — English action/cause verbs; Hindi cause/action terms
        $hasHowWhy = preg_match(
            '/\b(?:because|why|how|cause|reason|due to|following|amid|after|clashed|arrested|killed|died|injured|collapsed|attacked|injured|accused|alleged|protest|clash|explosion|fire|flood|accident|crash)\b/i',
            $contentLower
        ) === 1
        || preg_match(
            '/\b(?:क्योंकि|इसलिए|कारण|वजह|कैसे|हिंसक|हमला|हादसा|मौत|हत्या|घायल|गिरफ्तार|आग|बाढ़|दुर्घटना|विस्फोट|टक्कर|प्रदर्शन|झड़प)\b/u',
            $content
        ) === 1;

        return [
            'who_what' => $hasWho,
            'when'     => $hasWhen,
            'where'    => $hasWhere,
            'how_why'  => $hasHowWhy,
            'passed'   => ($hasWho && $hasWhen && $hasWhere && $hasHowWhy),
        ];
    }
}
