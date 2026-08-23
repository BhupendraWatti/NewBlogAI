<?php

declare(strict_types=1);

namespace App\Modules\PromptManager\Support;

final class UniversalNewsPrompt
{
    public const NAME = 'Standard Universal Article Generator';

    public const VERSION = 'v2.0';

    /**
     * @return list<string>
     */
    public static function variables(): array
    {
        return [
            'topic',
            'headline',
            'summary',
            'category',
            'language',
            'website',
            'tone',
            'keywords',
            'sources',
            'date',
            'research_context',
        ];
    }

    public static function template(): string
    {
        return <<<'PROMPT'
Create an original, publication-ready news report for {{website}} about {{topic}}.

EDITORIAL BRIEF
- Working headline/topic: {{headline}}
- Source summary: {{summary}}
- Category: {{category}}
- Publication date: {{date}}
- Output language: {{language}}
- Editorial tone: {{tone}}
- Focus terms: {{keywords}}
- Available source domains: {{sources}}

PRIMARY EVIDENCE
{{research_context}}

REPORTING METHOD
1. Identify the newest verified development in the evidence. Lead with it. If the evidence describes an older event, frame it explicitly as background or a follow-up; never present it as breaking news.
2. Use the inverted pyramid. The opening must answer as many of Who, What, When, Where, Why, and How as the evidence supports. Follow with context, consequences, and what is verifiably expected next.
3. Treat the evidence as the boundary of truth. Do not add facts from memory, guess missing details, merge separate people or events, or convert allegations into established facts.
4. Attribute material claims to the supplied source domains. Use direct quotations only when the exact wording appears in the evidence; keep any quotation brief. Otherwise paraphrase faithfully.
5. Use exact dates when timing could be ambiguous. Distinguish the event date, publication date, and current date. Verify mutable facts such as titles, officeholders, prices, rankings, and totals from the evidence or omit them.
6. Represent meaningful disagreement or uncertainty fairly. Name what is unknown, disputed, preliminary, or still developing instead of filling gaps.
7. Make the report useful to both search and human readers: answer the core query early, explain why the development matters, use descriptive subheadings, and incorporate focus terms naturally without repetition or keyword stuffing.
8. Make the headline accurate, specific, and shareable without clickbait. Do not overstate causality, certainty, scale, urgency, or impact.
9. Keep the depth proportional to the evidence. A short, fully supported report is better than a long article padded with generic commentary.
10. Write independently. Do not reproduce source sentences or imitate a publisher's distinctive phrasing.

OUTPUT
- Return only clean Markdown; no code fence, planning notes, or commentary.
- Begin with one # headline, followed immediately by a concise lead paragraph.
- Use 2–4 informative ## sections when the evidence supports them.
- Keep paragraphs short and transitions clear.
- End with ## Key Takeaways containing 3–5 evidence-backed bullets.
- Do not create a sources list or URL that was not supplied in the evidence.
PROMPT;
    }
}
