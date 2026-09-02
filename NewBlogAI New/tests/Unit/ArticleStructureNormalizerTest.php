<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\ContentPipeline\Services\ArticleStructureNormalizer;
use PHPUnit\Framework\TestCase;

class ArticleStructureNormalizerTest extends TestCase
{
    public function test_brief_report_is_de_fragmented_and_recap_is_removed(): void
    {
        $markdown = <<<'MD'
# दुर्घटना की खबर

**यह पूरी शुरुआती जानकारी अनावश्यक रूप से बोल्ड है।**

## दुर्घटना का विवरण

यह एक छोटा सत्यापित विवरण है।

## वाहन और मार्ग

कार नागपुर से सीहोर जा रही थी।

## Key Takeaways

- तीन लोगों की मौत हुई।
- छह लोग घायल हुए।

*Disclosure: This report was synthesized with AI assistance and is undergoing human verification.*
MD;

        $normalized = (new ArticleStructureNormalizer)->normalize($markdown);

        $this->assertStringNotContainsString('## ', $normalized);
        $this->assertStringNotContainsString('Key Takeaways', $normalized);
        $this->assertStringNotContainsString('Disclosure:', $normalized);
        $this->assertStringNotContainsString('**', $normalized);
        $this->assertStringContainsString('यह एक छोटा सत्यापित विवरण है।', $normalized);
        $this->assertStringContainsString('कार नागपुर से सीहोर जा रही थी।', $normalized);
    }

    public function test_detailed_report_keeps_substantive_sections(): void
    {
        $body = implode(' ', array_fill(0, 220, 'verified fact'));
        $markdown = "# Detailed report\n\n## First development\n\n{$body}\n\n## Official response\n\n{$body}";

        $normalized = (new ArticleStructureNormalizer)->normalize($markdown);

        $this->assertStringContainsString('## First development', $normalized);
        $this->assertStringContainsString('## Official response', $normalized);
    }
}
