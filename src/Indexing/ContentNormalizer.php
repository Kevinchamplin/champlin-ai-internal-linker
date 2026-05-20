<?php
/**
 * Normalize WordPress post content for embedding.
 *
 * Strips Gutenberg block markers, shortcodes, HTML, and collapses whitespace.
 * The output is what gets hashed (content_hash) AND embedded — so it must be
 * deterministic.
 *
 * @package Champlin\InternalLinker\Indexing
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Indexing;

final class ContentNormalizer
{
    public function normalize(string $raw_content): string
    {
        // Drop block grammar comments (<!-- wp:paragraph --> etc.).
        $content = preg_replace('/<!--\s*\/?wp:[^>]*-->/', ' ', $raw_content) ?? $raw_content;

        // Expand or strip shortcodes (we treat them as opaque text).
        if (function_exists('strip_shortcodes')) {
            $content = strip_shortcodes($content);
        }

        // Strip HTML tags but keep their text content.
        $content = wp_strip_all_tags($content, true);

        // Decode HTML entities.
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse all runs of whitespace (incl. NBSPs) to a single space.
        $content = preg_replace('/\xC2\xA0/', ' ', $content) ?? $content;
        $content = preg_replace('/\s+/u', ' ', $content) ?? $content;

        return trim((string) $content);
    }

    public function hash(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    /**
     * Split normalized content into ≤ $max_chars chunks at sentence boundaries.
     *
     * Used when content exceeds the embedding model's token limit. We chunk on
     * sentence boundaries to preserve semantic meaning, then the caller
     * mean-pools the resulting per-chunk vectors.
     *
     * @return string[]
     */
    public function chunk(string $normalized, int $max_chars = 6000): array
    {
        if ($normalized === '' || mb_strlen($normalized) <= $max_chars) {
            return $normalized === '' ? [] : [$normalized];
        }

        // Split on sentence-terminal punctuation followed by whitespace.
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z\(])/u', $normalized) ?: [$normalized];

        $chunks  = [];
        $buffer  = '';
        foreach ($sentences as $sentence) {
            $candidate = $buffer === '' ? $sentence : $buffer . ' ' . $sentence;
            if (mb_strlen($candidate) > $max_chars && $buffer !== '') {
                $chunks[] = $buffer;
                $buffer   = $sentence;
            } else {
                $buffer = $candidate;
            }
        }
        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    /**
     * Split content into sentences for anchor-extraction.
     *
     * @return string[]
     */
    public function sentences(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z\(])/u', $normalized) ?: [];
        return array_values(array_filter(array_map('trim', $sentences), static fn(string $s): bool => $s !== ''));
    }
}
