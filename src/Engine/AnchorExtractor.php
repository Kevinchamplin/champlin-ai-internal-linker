<?php
/**
 * Pick the best anchor phrase from the source post for linking to a target.
 *
 * Strategy: embed the target's (title + excerpt), embed each candidate
 * sentence in the source, return the sentence with the highest cosine
 * similarity to the target. Falls back to the target title if either
 * embedding step fails (no API key, network error, etc.).
 *
 * The anchor_offset is a character offset within the SOURCE's raw post_content
 * (not normalized content) where the chosen sentence begins. The block-editor
 * sidebar uses this to locate the insertion point.
 *
 * @package Champlin\InternalLinker\Engine
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Engine;

use Champlin\InternalLinker\Embeddings\ProviderFactory;
use Champlin\InternalLinker\Indexing\ContentNormalizer;
use Champlin\InternalLinker\Similarity\CosineCalculator;
use Throwable;
use WP_Post;

final class AnchorExtractor
{
    public function __construct(
        private ProviderFactory $provider_factory,
        private CosineCalculator $cosine,
        private ContentNormalizer $normalizer
    ) {
    }

    /**
     * @return array{anchor: string, offset: int}
     */
    public function extract(int $source_post_id, int $target_post_id): array
    {
        $source = get_post($source_post_id);
        $target = get_post($target_post_id);

        if (!$source instanceof WP_Post || !$target instanceof WP_Post) {
            return ['anchor' => '', 'offset' => 0];
        }

        $fallback = ['anchor' => $this->title_phrase($target), 'offset' => 0];

        if (!$this->provider_factory->is_configured()) {
            return $fallback;
        }

        $normalized = $this->normalizer->normalize($source->post_content);
        $sentences  = $this->normalizer->sentences($normalized);
        if ($sentences === []) {
            return $fallback;
        }

        $target_text = trim(($target->post_title ?: '') . '. ' . $this->target_excerpt($target));
        if ($target_text === '') {
            return $fallback;
        }

        try {
            $provider       = $this->provider_factory->create();
            $target_vector  = $provider->embed($target_text);

            $best_score    = -INF;
            $best_sentence = $this->title_phrase($target);
            foreach ($sentences as $sentence) {
                if (mb_strlen($sentence) < 12) {
                    continue;
                }
                $score = $this->cosine->similarity($target_vector, $provider->embed($sentence));
                if ($score > $best_score) {
                    $best_score    = $score;
                    $best_sentence = $sentence;
                }
            }

            $offset = $this->offset_in_source($source->post_content, $best_sentence);

            return [
                'anchor' => $this->trim_to_phrase($best_sentence, $target),
                'offset' => $offset,
            ];
        } catch (Throwable $e) {
            error_log('[champlin-internal-linker] anchor extraction failed: ' . $e->getMessage());
            return $fallback;
        }
    }

    private function title_phrase(WP_Post $target): string
    {
        return trim((string) $target->post_title);
    }

    private function target_excerpt(WP_Post $target): string
    {
        $excerpt = trim((string) $target->post_excerpt);
        if ($excerpt !== '') {
            return $excerpt;
        }
        $normalized = $this->normalizer->normalize($target->post_content);
        return mb_substr($normalized, 0, 400);
    }

    private function offset_in_source(string $raw_content, string $sentence): int
    {
        // Fuzzy locate: collapse whitespace in both, search for the first 60 chars.
        $needle = trim(preg_replace('/\s+/u', ' ', mb_substr($sentence, 0, 60)) ?? '');
        if ($needle === '') {
            return 0;
        }
        $haystack = preg_replace('/\s+/u', ' ', $raw_content) ?? $raw_content;
        $pos      = mb_stripos($haystack, $needle);
        return $pos === false ? 0 : (int) $pos;
    }

    /**
     * Try to keep the anchor short and on-topic. If the target's title appears
     * inside the chosen sentence (case-insensitive), use that exact substring.
     * Otherwise return the first ≤ 80 chars of the sentence.
     */
    private function trim_to_phrase(string $sentence, WP_Post $target): string
    {
        $title = trim((string) $target->post_title);
        if ($title !== '') {
            $pos = mb_stripos($sentence, $title);
            if ($pos !== false) {
                return mb_substr($sentence, $pos, mb_strlen($title));
            }
        }

        if (mb_strlen($sentence) <= 80) {
            return $sentence;
        }
        return rtrim(mb_substr($sentence, 0, 77)) . '…';
    }
}
