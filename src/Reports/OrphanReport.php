<?php
/**
 * Compute the orphan-page report: every published post (of the configured
 * post types) that has zero internal links pointing to it.
 *
 * Each orphan is augmented with up to N "inbound candidates" — the posts on
 * the site most semantically related to the orphan. The user is expected to
 * open one of those candidates in the editor; the AI Linker sidebar will then
 * surface the orphan as a top suggestion to link to, closing the loop with one
 * click. This makes the report a workflow, not just a list.
 *
 * @package Champlin\InternalLinker\Reports
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Reports;

use Champlin\InternalLinker\Embeddings\ProviderFactory;
use Champlin\InternalLinker\Similarity\CosineCalculator;
use Champlin\InternalLinker\Storage\VectorStore;

final class OrphanReport
{
    private const CANDIDATE_LIMIT = 3;
    private const CANDIDATE_THRESHOLD = 0.55;

    public function __construct(
        private LinkGraphScanner $scanner,
        private ?VectorStore $vector_store = null,
        private ?CosineCalculator $cosine = null
    ) {
    }

    /**
     * @return array{
     *   computed_at: string,
     *   scanned: int,
     *   total_eligible: int,
     *   orphan_count: int,
     *   orphans: array<int, array{post_id: int, title: string, permalink: string, post_type: string, modified: string, has_embedding: bool, candidates: array<int, array{post_id: int, title: string, edit_url: string, similarity: float}>}>
     * }
     */
    public function generate(bool $force_rescan = false): array
    {
        $graph      = $this->scanner->snapshot($force_rescan);
        $post_types = ProviderFactory::settings()['post_types'] ?? ['post'];

        $orphans = [];
        $total   = 0;
        $paged   = 1;

        do {
            $query = new \WP_Query([
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'paged'          => $paged,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            ]);

            if ($query->posts === []) {
                break;
            }

            foreach ($query->posts as $post) {
                $total++;
                $inbound = (int) ($graph['inbound_counts'][$post->ID] ?? 0);
                if ($inbound !== 0) {
                    continue;
                }
                $orphans[] = $this->build_orphan_row((int) $post->ID, $post);
            }
            $paged++;
        } while (count($query->posts) === 200);

        return [
            'computed_at'    => (string) $graph['computed_at'],
            'scanned'        => (int) $graph['scanned'],
            'total_eligible' => $total,
            'orphan_count'   => count($orphans),
            'orphans'        => $orphans,
        ];
    }

    /**
     * @return array{post_id: int, title: string, permalink: string, post_type: string, modified: string, has_embedding: bool, candidates: array}
     */
    private function build_orphan_row(int $post_id, \WP_Post $post): array
    {
        $candidates   = [];
        $has_embedding = false;

        if ($this->vector_store !== null && $this->cosine !== null) {
            $source = $this->vector_store->get($post_id);
            if ($source !== null) {
                $has_embedding = true;
                $candidates = $this->find_inbound_candidates($post_id, $source);
            }
        }

        return [
            'post_id'       => $post_id,
            'title'         => (string) $post->post_title,
            'permalink'     => (string) get_permalink($post),
            'post_type'     => (string) $post->post_type,
            'modified'      => (string) $post->post_modified_gmt,
            'has_embedding' => $has_embedding,
            'candidates'    => $candidates,
        ];
    }

    /**
     * Find the most semantically similar posts to the given source — these are
     * the best candidates to link TO the orphan (i.e. you'd open them, get a
     * suggestion to link to this orphan, and one-click fix it).
     *
     * @param array{vector: float[], model: string} $source
     * @return array<int, array{post_id: int, title: string, edit_url: string, similarity: float}>
     */
    private function find_inbound_candidates(int $orphan_id, array $source): array
    {
        $candidates = $this->vector_store->iterate_candidates(
            [$orphan_id],
            (string) $source['model']
        );

        $ranked = $this->cosine->rank(
            $source['vector'],
            $candidates,
            self::CANDIDATE_THRESHOLD,
            self::CANDIDATE_LIMIT
        );

        $rows = [];
        foreach ($ranked as $r) {
            $candidate_post = get_post((int) $r['post_id']);
            if (!$candidate_post) {
                continue;
            }
            $rows[] = [
                'post_id'    => (int) $r['post_id'],
                'title'      => (string) $candidate_post->post_title,
                'edit_url'   => (string) get_edit_post_link($candidate_post, 'url'),
                'similarity' => (float) $r['similarity'],
            ];
        }

        return $rows;
    }
}
