<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

use App\Modules\ContentPipeline\DTOs\PipelineContext;

class PromptEngine
{
    /**
     * Resolves system instructions or persona details (supporting overrides/options).
     */
    public function compileSystemPrompt(array $options = []): string
    {
        $base = $options['persona'] ?? 'You are a professional news journalist and editor. Your role is to write accurate, well-researched, and engaging news articles based on the provided research context and editorial guidelines. Always report facts objectively, attribute claims to sources, and write in a clear journalistic style appropriate for a global online news publication. You MUST write the news article using the detailed facts, timelines, and information provided in the "Research Context" block below. The "User Prompt" defines the headline and template structure, but all detailed facts must be drawn directly from the Research Context. Do NOT write from your training weights.';

        if (isset($options['persona'])) {
            return $base;
        }

        // ── JOURNALISTIC HONESTY GUARD ───────────────────────────────────────────────
        // This guard is mandatory and overrides any instruction in the user prompt.
        $honestGuard = <<<'GUARD'

CRITICAL JOURNALISTIC INTEGRITY RULES (non-negotiable):
1. FACT-ANCHORING: You MUST write ONLY from the facts present in the "Research Context" section below. Do NOT invent, extrapolate, or embellish any detail that is not explicitly stated in the source text.
2. NO FABRICATED QUOTES: Never invent direct quotes or official statements from government officials, police, or any person. If the Research Context contains no quote, do not include one.
3. NO FABRICATED STATISTICS: Do not invent numbers, percentages, distances, amounts, or dates that are not in the Research Context.
4. NO FABRICATED MILESTONES: Do not state that a building was inaugurated, a project was completed, or a law was passed unless that specific fact appears in the Research Context.
5. HONEST GAPS: If the Research Context is sparse, write a shorter, honest article. Use phrases like "details are still emerging" or "according to initial reports" rather than padding with invented details.
6. ATTRIBUTION: Attribute facts to "reports" or "[domain.com]" — NEVER claim a specific editorial brand (NDTV, The Hindu, Times of India) authored facts you are synthesising.
7. NO CIRCULAR LOGIC: Do not write cause-and-effect sentences where the cause and effect are the same thing rephrased.
8. COPYRIGHT & ORIGINALITY: Do NOT copy phrases or sentences verbatim from the Research Context. Restructure all facts and write them in your own words to prevent plagiarism.
9. BIAS & NEUTRALITY: Write with complete objectivity. Eliminate speculative adjectives, emotional language, and biased framing.
GUARD;

        return $base."\n".$honestGuard;
    }

    /**
     * Renders normalized source links, publisher details, published dates, and topic clusters/regions.
     */
    public function compileResearchContext(PipelineContext $context): string
    {
        $researchContext = '';

        if (! empty($context->sources)) {
            $researchContext .= "Source References:\n";
            // Limit to top 3 sources to keep prompt tokens within the target budget
            $sources = array_slice($context->sources, 0, 3);
            foreach ($sources as $source) {
                $title = $source['title'] ?? 'No Title';
                $url = $source['url'] ?? 'No URL';
                $snippet = $source['snippet'] ?? '';

                // Extract publisher details and published dates
                $publisher = $source['publisher'] ?? $source['metadata']['publisher'] ?? null;
                $publishedDate = $source['published_date'] ?? $source['metadata']['published_date'] ?? $source['publishedDate'] ?? null;
                $region = $source['metadata']['region'] ?? null;
                $locale = $source['metadata']['locale'] ?? null;

                $details = [];
                if ($publisher) {
                    $details[] = "Publisher: {$publisher}";
                }
                if ($publishedDate) {
                    $details[] = "Date: {$publishedDate}";
                }
                if ($region || $locale) {
                    $details[] = 'Region: '.($region ?? 'N/A').' ('.($locale ?? 'N/A').')';
                }

                $detailsStr = ! empty($details) ? ' ['.implode(', ', $details).']' : '';
                $researchContext .= "- {$title} ({$url}){$detailsStr}: {$snippet}\n";
            }
        } else {
            $researchContext .= "No sources collected.\n";
        }

        // ── SCRAPED ARTICLE BODY ───────────────────────────────────────────────────
        // Real article body text fetched at generation time by ContentGeneratorService.
        // This is the PRIMARY fact source the AI must write from.
        $scrapedBody = trim($context->metadata['scraped_article_body'] ?? '');
        if (! empty($scrapedBody)) {
            $researchContext .= "\n--- ARTICLE BODY TEXT (PRIMARY FACT SOURCE) ---\n";
            $researchContext .= 'The following is the ACTUAL text scraped from the source article(s). ';
            $researchContext .= "You MUST use ONLY the facts contained here. Do NOT add details not present in this text.\n\n";
            $researchContext .= $scrapedBody."\n";
            $researchContext .= "--- END OF ARTICLE BODY TEXT ---\n";
        } else {
            $researchContext .= "\n[NOTE: No article body text could be retrieved from the source URLs. ";
            $researchContext .= 'Write ONLY what is verifiable from the headline and summary. ';
            $researchContext .= "Do NOT invent facts, quotes, statistics, or milestones.]\n";
        }

        // Add topic clusters/regions
        $topicClusters = $context->metadata['topic_clusters'] ?? [];
        if (! empty($topicClusters)) {
            $researchContext .= "\nTopic Clusters:\n";
            foreach ($topicClusters as $clusterName => $urls) {
                $researchContext .= "- {$clusterName}: ".implode(', ', $urls)."\n";
            }
        }

        return $researchContext;
    }

    /**
     * Renders extracted facts (People, Orgs, Locations, Dates, Events) and key terms.
     */
    public function compileContextInjection(PipelineContext $context): string
    {
        $facts = $context->metadata['extracted_facts'] ?? $context->researchData['extracted_facts'] ?? [];

        if (empty($facts)) {
            return "No extracted facts available.\n";
        }

        $injection = "Extracted Facts:\n";
        foreach (['people', 'organizations', 'locations', 'dates', 'events', 'keywords'] as $type) {
            if (! empty($facts[$type])) {
                // Determine label: e.g. organizations -> Organizations, keywords -> Key Terms
                $label = $type === 'keywords' ? 'Key Terms' : ucfirst($type);
                // Limit to top 10 items to prevent token bloat
                $limitedFacts = array_slice($facts[$type], 0, 10);
                $injection .= "- {$label}: ".implode(', ', $limitedFacts)."\n";
            }
        }

        return $injection;
    }

    /**
     * Interpolates user template placeholders like {{topic}}, {{category}}, {{language}}, and {{website}}.
     */
    public function compileUserPrompt(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace(["@{{{$key}}}", "{{{$key}}}"], (string) $value, $template);
        }

        return $template;
    }

    /**
     * Generates dynamic guidelines based on context (e.g. locale target, language translations, style guides, tone instructions).
     *
     * Enhanced to surface temporal framing context set by ChronologicalContextParser:
     * - story_type: 'breaking' | 'followup' | 'background'
     * - dynamic_instructions: may contain a TEMPORAL FRAMING GUARDRAIL for follow-up stories
     */
    public function compileDynamicInstructions(PipelineContext $context): string
    {
        $instructions = [];

        // Language guidelines
        $language = $context->metadata['language'] ?? $context->pipeline->language ?? null;
        if ($language) {
            $languageName = ['en' => 'English', 'hi' => 'Hindi'][$language] ?? $language;
            $instructions[] = "Language: Write the complete article in {$languageName} (code '{$language}'). Translate every visible heading, label, bullet, and caption into this language; do not leave English structural labels in non-English output.";
        }

        // Category context
        $category = $context->metadata['news_category'] ?? $context->pipeline->news_category ?? null;
        if ($category) {
            $instructions[] = "News Category: This is a '{$category}' category news article. Adopt the appropriate tone and framing for this category.";
        }

        // Style guides
        $styleGuide = $context->metadata['style_guide'] ?? $context->metadata['style'] ?? null;
        if ($styleGuide) {
            $instructions[] = "Style Guide: {$styleGuide}";
        }

        // Tone instructions
        $tone = $context->metadata['tone'] ?? $context->metadata['tone_instruction'] ?? null;
        if ($tone) {
            $instructions[] = "Tone: Write with a {$tone} tone.";
        }

        // Geographic targeting context
        $targetCountry = $context->pipeline?->target_country ?? $context->metadata['target_country'] ?? null;
        $targetState = $context->pipeline?->target_state ?? $context->metadata['target_state'] ?? null;
        if ($targetState || $targetCountry) {
            $location = $targetState && $targetCountry ? "{$targetState}, {$targetCountry}" : ($targetState ?: $targetCountry);
            $instructions[] = "Target Region: This article is targeted for {$location}. Ensure regional relevance, local context, and geographic accuracy are accurately reflected.";
        }

        // ── Temporal Framing Guardrail ────────────────────────────────────────
        // Populated by ChronologicalContextParser. For follow-up/background
        // stories this contains mandatory framing rules that prevent the AI
        // writer from treating historical events as breaking news.
        $storyType = $context->metadata['story_type'] ?? 'breaking';
        $guardrailText = trim($context->metadata['dynamic_instructions'] ?? '');

        if ($storyType === 'breaking') {
            // Confirm this is a live story so the AI approaches it with urgency
            $instructions[] = 'Story Classification: BREAKING / CURRENT EVENT — write with immediacy. This story covers events that are happening now or very recently.';
            if (! empty($guardrailText)) {
                $instructions[] = 'Additional Guidelines: '.$guardrailText;
            }
        } elseif (! empty($guardrailText)) {
            // The full TEMPORAL FRAMING GUARDRAIL from ChronologicalContextParser
            // is injected verbatim so the AI writer receives the exact framing rules.
            $instructions[] = $guardrailText;
        } else {
            // story_type is followup/background but parser found no guardrail text
            // (edge case: parser ran but wrote nothing). Emit a safe generic rule.
            if ($storyType === 'followup') {
                $instructions[] = "Story Classification: FOLLOW-UP / DEVELOPMENTAL — this is a follow-up story on a past event. Lead with today's latest development and reference the original incident as historical context. Do NOT write as if the original event happened today.";
            }
        }

        // Note: dynamic_instructions is intentionally NOT read again here — its
        // content was already consumed by the guardrail block above.
        $additional = $context->metadata['instructions'] ?? null;
        if ($additional) {
            $instructions[] = "Additional Guidelines: {$additional}";
        }

        if (empty($instructions)) {
            return 'Write natural, engaging, and professional content appropriate for the topic.';
        }

        return implode("\n", $instructions);
    }

    /**
     * Formats final markdown instructions.
     */
    public function compileOutputInstructions(array $options = [], ?PipelineContext $context = null): string
    {
        $instructions = $options['instructions'] ?? 'Format the news article using clean, readable Markdown. Keep structure proportional to the verified evidence. Use one accurate # headline and a concise lead, then advance the reporting with new facts in each paragraph. Use ## headings only when the evidence supports genuinely distinct, substantial sections; do not create a heading for a single short paragraph. Do not add a Key Takeaways, recap, summary, conclusion, or bullet list that repeats the article. Avoid repeating facts from the headline or lead. Use bold sparingly—never bold full paragraphs or routine facts. Write every visible heading and label in the requested article language. Do not output HTML tags or markdown code fences. Output only the raw Markdown article.';

        $instructions .= "\nEvidence boundary: use only the supplied source text. Never invent names, dates, quotations, locations, statistics, or office titles. Current Date: ".now()->format('F j, Y').'. Verify every current officeholder and title from the supplied evidence; omit any current designation the evidence does not establish.';

        if (($context?->metadata['evidence_mode'] ?? null) === 'summary_only') {
            $instructions .= "\nSUMMARY-ONLY EVIDENCE MODE: The source body could not be retrieved. Produce a compact brief using only the verified headline and summary. Do not use H2 headings. Do not use bullet points. Do not add a recap, summary bullets, or Key Takeaways. Do not repeat the same fact in different wording. Explicitly say that further details are unavailable only when needed for clarity.";
        }

        if (isset($options['additional_output_instructions'])) {
            $instructions .= ' '.$options['additional_output_instructions'];
        }

        return $instructions;
    }

    /**
     * Combines all modular sections cleanly.
     */
    public function buildFullPrompt(PipelineContext $context, string $userTemplate, array $variables): string
    {
        // Extract override options if passed via metadata/options
        $options = $context->metadata['prompt_options'] ?? [];

        $systemPrompt = $this->compileSystemPrompt($options);
        $researchContext = $this->compileResearchContext($context);
        $contextInjection = $this->compileContextInjection($context);
        $userPrompt = $this->compileUserPrompt($userTemplate, $variables);
        $dynamicInstructions = $this->compileDynamicInstructions($context);
        $outputInstructions = $this->compileOutputInstructions($options, $context);

        return implode("\n\n", [
            "System Prompt:\n".$systemPrompt,
            "Research Context:\n".$researchContext,
            "Context Injection:\n".$contextInjection,
            "User Prompt:\n".$userPrompt,
            "Dynamic Instructions:\n".$dynamicInstructions,
            "Output Instructions:\n".$outputInstructions,
        ]);
    }
}
