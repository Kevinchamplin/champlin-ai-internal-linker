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

use Champlin\InternalLinker\Embeddings\ProviderFactory;
use Champlin\InternalLinker\Integrations\TargetKeywordReader;
use Champlin\InternalLinker\Similarity\CosineCalculator;
use Champlin\InternalLinker\Storage\VectorStore;

final class SuggestionEngine
{
    public function __construct(
        private VectorStore $vector_store,
        private CosineCalculator $cosine,
        private AnchorExtractor $anchor_extractor,
        private ?TargetKeywordReader $keyword_reader = null
    ) {
    }

    /**
     * @param int[] $exclude_ids Post IDs to skip on top of the source itself.
     * @return array<int, array{post_id: int, title: string, permalink: string, similarity: float, suggested_anchor: string, anchor_offset: int, target_keyword: string, target_keyword_source: string}>
     */
    public function suggestions_for(int $source_post_id, int $limit, float $threshold, array $exclude_ids = []): array
    {
        $source = $this->vector_store->get($source_post_id);
        if ($source === null) {
            return [];
        }

        $settings        = ProviderFactory::settings();
        $ignored_posts   = (array) ($settings['ignored_post_ids'] ?? []);
        $ignored_terms   = (array) ($settings['ignored_term_ids'] ?? []);
        $term_excluded   = $this->expand_term_exclusions($ignored_terms, (array) ($settings['post_types'] ?? ['post']));

        $base_excluded = array_values(array_unique(array_merge(
            [$source_post_id],
            array_map('intval', $exclude_ids),
            array_map('intval', $ignored_posts),
            $term_excluded
        )));

        /**
         * Filter the set of excluded candidate post IDs for a suggestion query.
         *
         * Pro add-ons hook here to inject feature-specific exclusions (e.g.
         * "Money Pages" that should never be hidden from suggestions, or
         * orphan-only mode that excludes well-linked posts).
         *
         * @param int[]   $excluded       Post IDs to skip during ranking.
         * @param int     $source_post_id The post the user is editing.
         * @param array   $settings       Current cil_settings option.
         */
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public extension hook; "cil_" is this plugin's established public-API prefix (LinkWeaver Pro depends on it).
        $excluded = (array) apply_filters('cil_extra_excluded_ids', $base_excluded, $source_post_id, $settings);
        $excluded = array_values(array_unique(array_map('intval', $excluded)));

        $ranked = $this->cosine->rank(
            $source['vector'],
            $this->vector_store->iterate_candidates($excluded, $source['model']),
            $threshold,
            $limit
        );

        /**
         * Filter the ranked candidate list before per-candidate post lookup and
         * anchor extraction.
         *
         * Pro add-ons hook here to:
         *   - boost Money Pages above their pure-cosine rank
         *   - inject focus-keyword match bonuses
         *   - downrank candidates by penalty rules
         *
         * Each entry is shaped `['post_id' => int, 'similarity' => float]`.
         * Implementations must preserve that shape; re-sort if scores change.
         *
         * @param array $ranked          Cosine-ranked candidates, descending.
         * @param int   $source_post_id  The post the user is editing.
         * @param array $settings        Current cil_settings option.
         */
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public extension hook; "cil_" is this plugin's established public-API prefix (LinkWeaver Pro depends on it).
        $ranked = (array) apply_filters('cil_rank_results', $ranked, $source_post_id, $settings);

        $keyword_reader = $this->keyword_reader ?? new TargetKeywordReader();

        $results = [];
        foreach ($ranked as $row) {
            $post = get_post($row['post_id']);
            if (!$post) {
                continue;
            }
            $anchor    = $this->anchor_extractor->extract($source_post_id, $row['post_id']);
            $keyword   = $keyword_reader->keyword_for($row['post_id']);
            $kw_source = $keyword === '' ? '' : $keyword_reader->source_for($row['post_id']);

            $row_out = [
                'post_id'                => $row['post_id'],
                'title'                  => (string) $post->post_title,
                'permalink'              => (string) get_permalink($post),
                'similarity'             => (float) $row['similarity'],
                'suggested_anchor'       => $anchor['anchor'],
                'anchor_offset'          => $anchor['offset'],
                'target_keyword'         => $keyword,
                'target_keyword_source'  => $kw_source,
            ];
            /**
             * Filter a single suggestion row before it's added to the response.
             *
             * Pro add-ons hook here to attach extra display data (badges, click
             * predictions, broken-link warnings).
             *
             * @param array $row_out Suggestion row shape — must preserve the keys above.
             * @param array $row     Original ranked entry (post_id + similarity).
             * @param int   $source_post_id
             */
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public extension hook; "cil_" is this plugin's established public-API prefix (LinkWeaver Pro depends on it).
            $results[] = (array) apply_filters('cil_suggestion_row', $row_out, $row, $source_post_id);
        }

        return $results;
    }

    /**
     * @param array<int, int|string> $term_ids
     * @param string[] $post_types
     * @return int[]
     */
    private function expand_term_exclusions(array $term_ids, array $post_types): array
    {
        $term_ids = array_values(array_filter(array_map('intval', $term_ids), static fn(int $i): bool => $i > 0));
        if ($term_ids === []) {
            return [];
        }

        $query = new \WP_Query([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Necessary taxonomy filter; runs in admin/indexing context, not on front-end page loads.
            'tax_query'      => [
                [
                    'taxonomy'         => 'category',
                    'field'            => 'term_id',
                    'terms'            => $term_ids,
                    'include_children' => true,
                ],
            ],
        ]);

        return array_map('intval', $query->posts);
    }
}
