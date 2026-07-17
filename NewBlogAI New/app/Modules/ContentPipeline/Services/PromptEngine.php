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
        return $options['persona'] ?? 'You are a professional news journalist and editor. Your role is to write accurate, well-researched, and engaging news articles based on the provided research context and editorial guidelines. Always report facts objectively, attribute claims to sources, and write in a clear journalistic style appropriate for a global online news publication.';
    }

    /**
     * Renders normalized source links, publisher details, published dates, and topic clusters/regions.
     */
    public function compileResearchContext(PipelineContext $context): string
    {
        $researchContext = "Research Context:\n";
        
        if (!empty($context->sources)) {
            $researchContext .= "Normalized Sources:\n";
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
                    $details[] = "Region: " . ($region ?? 'N/A') . " (" . ($locale ?? 'N/A') . ")";
                }
                
                $detailsStr = !empty($details) ? " [" . implode(', ', $details) . "]" : "";
                $researchContext .= "- {$title} ({$url}){$detailsStr}: {$snippet}\n";
            }
        } else {
            $researchContext .= "No sources collected.\n";
        }

        // Add topic clusters/regions
        $topicClusters = $context->metadata['topic_clusters'] ?? [];
        if (!empty($topicClusters)) {
            $researchContext .= "\nTopic Clusters:\n";
            foreach ($topicClusters as $clusterName => $urls) {
                $researchContext .= "- {$clusterName}: " . implode(', ', $urls) . "\n";
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
            if (!empty($facts[$type])) {
                // Determine label: e.g. organizations -> Organizations, keywords -> Key Terms
                $label = $type === 'keywords' ? 'Key Terms' : ucfirst($type);
                // Limit to top 10 items to prevent token bloat
                $limitedFacts = array_slice($facts[$type], 0, 10);
                $injection .= "- {$label}: " . implode(', ', $limitedFacts) . "\n";
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
            $instructions[] = "Language: The news article must be written in language code '{$language}'.";
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

        // ── Temporal Framing Guardrail ────────────────────────────────────────
        // Populated by ChronologicalContextParser. For follow-up/background
        // stories this contains mandatory framing rules that prevent the AI
        // writer from treating historical events as breaking news.
        $storyType  = $context->metadata['story_type'] ?? 'breaking';
        $guardrailText = trim($context->metadata['dynamic_instructions'] ?? '');

        if ($storyType === 'breaking') {
            // Confirm this is a live story so the AI approaches it with urgency
            $instructions[] = "Story Classification: BREAKING / CURRENT EVENT — write with immediacy. This story covers events that are happening now or very recently.";
            if (!empty($guardrailText)) {
                $instructions[] = "Additional Guidelines: " . $guardrailText;
            }
        } elseif (!empty($guardrailText)) {
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
            return "Write natural, engaging, and professional content appropriate for the topic.";
        }

        return implode("\n", $instructions);
    }

    /**
     * Formats final markdown instructions.
     */
    public function compileOutputInstructions(array $options = []): string
    {
        $instructions = $options['instructions'] ?? "Format the news article using clean, readable Markdown. Structure with a compelling headline (# H1), a concise lead paragraph answering Who/What/When/Where/Why, followed by supporting sections (## H2 subheadings). Use short paragraphs (2-3 sentences). Include a 'Key Takeaways' bullet list at the end. Do not output HTML tags. Do not wrap in markdown code blocks. Output only the raw Markdown content of the news article.";
        
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
        $outputInstructions = $this->compileOutputInstructions($options);

        return implode("\n\n", [
            "System Prompt:\n" . $systemPrompt,
            "Research Context:\n" . $researchContext,
            "Context Injection:\n" . $contextInjection,
            "User Prompt:\n" . $userPrompt,
            "Dynamic Instructions:\n" . $dynamicInstructions,
            "Output Instructions:\n" . $outputInstructions
        ]);
    }
}
