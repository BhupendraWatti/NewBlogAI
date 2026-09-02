<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Services;

/**
 * Enforces a small set of deterministic editorial structure guarantees after
 * generation. It does not rewrite facts; it only removes presentation clutter.
 */
final class ArticleStructureNormalizer
{
    public function normalize(string $markdown, string $language = 'en', bool $summaryOnly = false): string
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return $markdown;
        }

        // Recap lists repeat the lead in brief news and were previously forced
        // by two prompt layers. Remove common translated variants as a section.
        $markdown = preg_replace(
            '/^##\s*(?:Key\s+Takeaways|मुख्य\s+बिंदु|महत्वपूर्ण\s+बिंदु|सारांश)\s*$.*?(?=^##\s|\z)/imsu',
            '',
            $markdown,
        ) ?? $markdown;

        // Disclosure is publication policy, not article copy. Remove the legacy
        // sentence even when an older/custom prompt still asks the model for it.
        $markdown = preg_replace(
            '/^\s*\*?(?:Disclosure:\s*)?This report was synthesized with AI assistance and is undergoing human verification\.\*?\s*$/imu',
            '',
            $markdown,
        ) ?? $markdown;

        $wordCount = count(array_filter(preg_split('/\s+/u', strip_tags($markdown)) ?: []));
        $isBrief = $summaryOnly || $wordCount < 350;

        if ($isBrief) {
            // Preserve section prose but remove headings that fragment a brief.
            $markdown = preg_replace('/^##\s+.+$\R?/mu', '', $markdown) ?? $markdown;
        }

        preg_match_all('/\*\*(.+?)\*\*/su', $markdown, $boldMatches);
        $boldSegments = $boldMatches[1] ?? [];
        $boldCharacters = array_sum(array_map('mb_strlen', $boldSegments));
        $plainCharacters = max(1, mb_strlen(preg_replace('/\s+/u', '', $markdown) ?? $markdown));

        if ($isBrief || count($boldSegments) >= 5 || ($boldCharacters / $plainCharacters) > 0.20) {
            $markdown = preg_replace('/\*\*(.+?)\*\*/su', '$1', $markdown) ?? $markdown;
        }

        // Normalise whitespace left after removed headings/sections.
        $markdown = preg_replace('/[ \t]+\R/u', "\n", $markdown) ?? $markdown;
        $markdown = preg_replace('/\R{3,}/u', "\n\n", $markdown) ?? $markdown;

        return trim($markdown);
    }
}
