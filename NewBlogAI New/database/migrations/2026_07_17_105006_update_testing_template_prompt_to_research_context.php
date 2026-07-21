<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update the "Testing Template" prompt to use fact-anchored synthesis
     * instead of the rigid template that was causing hallucinations.
     *
     * The new template uses {{research_context}} which is populated at generation
     * time with real scraped article body text from the candidate's source URLs.
     * This forces the AI to write from actual reported facts rather than inventing content.
     */
    public function up(): void
    {
        $newPrompt = <<<'PROMPT'
Write a factual, engaging, and SEO-optimized news article for {{website}} in {{language}}.

Today's date: {{date}}
Headline topic: {{headline}}
Category: {{category}}
Tone: {{tone}}
Focus keywords (use naturally, especially in headers): {{keywords}}

RESEARCH CONTEXT — PRIMARY FACT SOURCE:
The following contains the ACTUAL text scraped from the source article(s). You MUST base your article ONLY on the facts stated here.

{{research_context}}

WRITING INSTRUCTIONS:
1. Lead paragraph: Start with the single most important, verifiable fact from the Research Context above. Answer Who, What, When, Where, Why in 2–3 sentences.
2. Body paragraphs: Synthesise the remaining facts from the Research Context into 2–3 supporting paragraphs. Maintain the original meaning — do not embellish, exaggerate, or add invented details.
3. Attribution: Write "According to reports from [domain]..." or "As reported by [domain]..." using the domain names from the source tags in the Research Context. Never claim a specific editorial brand (NDTV, The Hindu, TOI) wrote something unless their exact text appears above.
4. Honest gaps: If a specific detail (quote, statistic, milestone) is NOT present in the Research Context, do NOT include it. Write "Details are still emerging" or omit that point entirely.
5. Key Takeaways: End with a brief bullet list (3–5 points) summarising the verifiable facts only.
6. Length: Let article length emerge naturally from available facts. Do NOT pad with invented content to reach a word count target.

OUTPUT FORMAT:
- Use clean Markdown (# for H1 headline, ## for subheadings)
- Short paragraphs (2–3 sentences each)
- No HTML tags
- No markdown code fences
- Output only the article content
PROMPT;

        // Update the "Testing Template" prompt
        $updated = DB::table('prompts')
            ->where('name', 'like', '%Testing%')
            ->update(['prompt' => $newPrompt, 'updated_at' => now()]);

        if ($updated === 0) {
            // If no "Testing Template" found, update the first available prompt as a fallback
            DB::table('prompts')
                ->orderBy('id')
                ->limit(1)
                ->update(['prompt' => $newPrompt, 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migration — restore a generic fallback prompt.
     * (Full original text is not stored here; just restore a safe default.)
     */
    public function down(): void
    {
        $fallback = 'Write a comprehensive, factual news article about {{category}} for {{website}}. '
            . 'Focus on the latest developments. Headline: {{headline}}. '
            . 'Write in {{language}} with a {{tone}} tone. Include {{keywords}} naturally.';

        DB::table('prompts')
            ->where('name', 'like', '%Testing%')
            ->update(['prompt' => $fallback, 'updated_at' => now()]);
    }
};
