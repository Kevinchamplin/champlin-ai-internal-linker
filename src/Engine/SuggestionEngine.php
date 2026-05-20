<?php
/**
 * Suggestion orchestration.
 *
 * Takes a source post, loads or computes its embedding, ranks candidates,
 * filters by threshold, and delegates anchor extraction.
 *
 * @package Champlin\InternalLinker\Engine
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Engine;

use Champlin\InternalLinker\Similarity\CosineCalculator;
use Champlin\InternalLinker\Storage\VectorStore;

final class SuggestionEngine
{
    public function __construct(
        private VectorStore $vector_store,
        private CosineCalculator $cosine,
        private AnchorExtractor $anchor_extractor
    ) {
    }

    /**
     * @param int[] $exclude_ids Post IDs to skip on top of the source itself.
     * @return array<int, array{post_id: int, title: string, permalink: string, similarity: float, suggested_anchor: string, anchor_offset: int}>
     */
    public function suggestions_for(int $source_post_id, int $limit, float $threshold, array $exclude_ids = []): array
    {
        $source = $this->vector_store->get($source_post_id);
        if ($source === null) {
            return [];
        }

        $excluded = array_unique(array_merge([$source_post_id], $exclude_ids));
        $ranked   = $this->cosine->rank(
            $source['vector'],
            $this->vector_store->iterate_candidates($excluded, $source['model']),
            $threshold,
            $limit
        );

        $results = [];
        foreach ($ranked as $row) {
            $post = get_post($row['post_id']);
            if (!$post) {
                continue;
            }
            $anchor = $this->anchor_extractor->extract($source_post_id, $row['post_id']);
            $results[] = [
                'post_id'          => $row['post_id'],
                'title'            => (string) $post->post_title,
                'permalink'        => (string) get_permalink($post),
                'similarity'       => (float) $row['similarity'],
                'suggested_anchor' => $anchor['anchor'],
                'anchor_offset'    => $anchor['offset'],
            ];
        }

        return $results;
    }
}
