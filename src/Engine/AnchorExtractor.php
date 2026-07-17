<?php
/**
 * Pick the best anchor phrase from the source post for linking to a target.
 *
 * Strategy:
 *   1. Embed every sentence in the source post ONCE per source (batched in a
 *      single OpenAI call, cached in a transient keyed by content_hash).
 *   2. For each target, compare those pre-computed source-sentence vectors
 *      against the target's already-stored full-content embedding from the
 *      vector store. Pick the sentence with the highest cosine.
 *
 * This is much cheaper than the naive approach (no per-target embedding,
 * no per-sentence API call). For an N-target / M-sentence source post:
 *   - Old:   N × M sequential embed() calls (~100 calls = 100 seconds)
 *   - New:   1 batched embed_batch() call regardless of N × M
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
use Champlin\InternalLinker\Storage\VectorStore;
use Throwable;
use WP_Post;

final class AnchorExtractor
{
    private const TRANSIENT_PREFIX = 'chail_src_sent_';
    private const TRANSIENT_TTL    = HOUR_IN_SECONDS * 12;

    /**
     * @var array<int, array{sentences: string[], vectors: float[][], offsets: int[]}>
     */
    private array $source_cache = [];

    public function __construct(
        private ProviderFactory $provider_factory,
        private CosineCalculator $cosine,
        private ContentNormalizer $normalizer,
        private ?VectorStore $vector_store = null
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

        if ($this->vector_store === null) {
            return $fallback;
        }

        // Use the target's already-stored full-content embedding rather than
        // re-embedding the title+excerpt — saves an API call per target.
        $target_row = $this->vector_store->get($target_post_id);
        if ($target_row === null) {
            return $fallback;
        }

        try {
            $bundle = $this->source_bundle($source);
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-gated diagnostic only.
                error_log('[champlin-internal-linker] anchor source-bundle failed: ' . $e->getMessage());
            }
            return $fallback;
        }
        if ($bundle === null || $bundle['vectors'] === []) {
            return $fallback;
        }

        $best_score = -INF;
        $best_index = -1;
        foreach ($bundle['vectors'] as $i => $sentence_vector) {
            $score = $this->cosine->similarity($target_row['vector'], $sentence_vector);
            if ($score > $best_score) {
                $best_score = $score;
                $best_index = $i;
            }
        }

        if ($best_index < 0) {
            return $fallback;
        }

        $best_sentence = $bundle['sentences'][$best_index];
        $best_offset   = $bundle['offsets'][$best_index];

        return [
            'anchor' => $this->trim_to_phrase($best_sentence, $target),
            'offset' => $best_offset,
        ];
    }

    /**
     * Compute (or fetch from transient) the source post's per-sentence
     * embeddings. One batched OpenAI call covers all sentences.
     *
     * @return array{sentences: string[], vectors: float[][], offsets: int[]}|null
     */
    private function source_bundle(WP_Post $source): ?array
    {
        $source_id = (int) $source->ID;
        if (isset($this->source_cache[$source_id])) {
            return $this->source_cache[$source_id];
        }

        $raw_content = (string) $source->post_content;
        $normalized  = $this->normalizer->normalize($raw_content);
        $hash        = $this->normalizer->hash($normalized);

        $transient_key = self::TRANSIENT_PREFIX . $source_id . '_' . substr($hash, 0, 16);
        $cached        = get_transient($transient_key);
        if (is_array($cached) && isset($cached['sentences'], $cached['vectors'], $cached['offsets'])) {
            $this->source_cache[$source_id] = $cached;
            return $cached;
        }

        $sentences = array_values(array_filter(
            $this->normalizer->sentences($normalized),
            static fn(string $s): bool => mb_strlen($s) >= 12
        ));
        if ($sentences === []) {
            return null;
        }

        // Cap to a sane upper bound to keep batched API calls fast even on
        // very long posts. ~80 sentences ≈ a ~1500-word draft.
        if (count($sentences) > 80) {
            $sentences = array_slice($sentences, 0, 80);
        }

        if (!$this->provider_factory->is_configured()) {
            return null;
        }

        $provider = $this->provider_factory->create();
        $vectors  = $provider->embed_batch($sentences);
        if (count($vectors) !== count($sentences)) {
            return null;
        }

        $offsets = $this->locate_offsets($raw_content, $sentences);

        $bundle = [
            'sentences' => $sentences,
            'vectors'   => $vectors,
            'offsets'   => $offsets,
        ];

        set_transient($transient_key, $bundle, self::TRANSIENT_TTL);
        $this->source_cache[$source_id] = $bundle;

        return $bundle;
    }

    /**
     * @param string[] $sentences
     * @return int[]
     */
    private function locate_offsets(string $raw_content, array $sentences): array
    {
        $collapsed = preg_replace('/\s+/u', ' ', $raw_content) ?? $raw_content;
        $offsets   = [];
        foreach ($sentences as $sentence) {
            $needle = trim(preg_replace('/\s+/u', ' ', mb_substr($sentence, 0, 60)) ?? '');
            $pos    = $needle === '' ? false : mb_stripos($collapsed, $needle);
            $offsets[] = $pos === false ? 0 : (int) $pos;
        }
        return $offsets;
    }

    private function title_phrase(WP_Post $target): string
    {
        return trim((string) $target->post_title);
    }

    /**
     * Pick the anchor text that the editor sidebar will try to wrap in place.
     *
     * Goal: return a string that is a LITERAL substring of the source post's
     * content, so the editor can find-and-link it without manual editing.
     *
     * Strategy, in order:
     *   1. If the target's title appears verbatim in the source sentence,
     *      return that exact substring (best: anchor reads as the linked
     *      page's title, in the editor's own prose).
     *   2. Otherwise, return the first 80 characters of the source sentence
     *      trimmed at the nearest word boundary — no ellipsis, since an
     *      ellipsis would break the literal substring guarantee.
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
        // Trim to <=80 chars at the last word boundary so the result remains
        // a clean substring of the original sentence (no ellipsis).
        $window = mb_substr($sentence, 0, 80);
        $last_space = mb_strrpos($window, ' ');
        if ($last_space !== false && $last_space >= 20) {
            return rtrim(mb_substr($window, 0, $last_space));
        }
        return rtrim($window);
    }
}
