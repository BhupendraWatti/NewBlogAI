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
        return $options['persona'] ?? 'You are a professional, expert copywriter and assistant. Your role is to write high-quality, engaging, and SEO-optimized content based on the provided research context, user prompt, and instructions.';
    }

    /**
     * Renders normalized source links, publisher details, published dates, and topic clusters/regions.
     */
    public function compileResearchContext(PipelineContext $context): string
    {
        $researchContext = "Research Context:\n";
        
        if (!empty($context->sources)) {
            $researchContext .= "Normalized Sources:\n";
            foreach ($context->sources as $source) {
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
                $injection .= "- {$label}: " . implode(', ', $facts[$type]) . "\n";
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
     */
    public function compileDynamicInstructions(PipelineContext $context): string
    {
        $instructions = [];

        // Language translations guidelines
        $language = $context->metadata['language'] ?? $context->pipeline->language ?? null;
        if ($language) {
            $instructions[] = "Language: The content must be written in language code '{$language}'.";
        }

        // Locale target
        $locale = $context->metadata['locale'] ?? $context->metadata['target_locale'] ?? null;
        if ($locale) {
            $instructions[] = "Locale: Target audience locale is '{$locale}'. Adhere to regional spelling conventions, terminology, and formatting style.";
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

        // Additional guidelines
        $additional = $context->metadata['dynamic_instructions'] ?? $context->metadata['instructions'] ?? null;
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
        $instructions = $options['instructions'] ?? "Format the article using clean, readable Markdown. Use appropriate headings (e.g. H2, H3) to structure the content. Do not output HTML tags, and do not wrap the content in standard markdown code blocks (e.g. ```markdown ... ```). The output should be the raw markdown content of the article itself.";
        
        if (isset($options['additional_output_instructions'])) {
            $instructions .= " " . $options['additional_output_instructions'];
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
