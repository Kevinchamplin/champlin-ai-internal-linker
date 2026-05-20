<?php
/**
 * Compute the orphan-page report: every published post (of the configured
 * post types) that has zero internal links pointing to it.
 *
 * @package Champlin\InternalLinker\Reports
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Reports;

use Champlin\InternalLinker\Embeddings\ProviderFactory;

final class OrphanReport
{
    public function __construct(private LinkGraphScanner $scanner)
    {
    }

    /**
     * @return array{
     *   computed_at: string,
     *   scanned: int,
     *   total_eligible: int,
     *   orphan_count: int,
     *   orphans: array<int, array{post_id: int, title: string, permalink: string, post_type: string, modified: string}>
     * }
     */
    public function generate(bool $force_rescan = false): array
    {
        $graph      = $this->scanner->snapshot($force_rescan);
        $post_types = ProviderFactory::settings()['post_types'] ?? ['post'];

        $orphans       = [];
        $total         = 0;
        $paged         = 1;

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
                if ($inbound === 0) {
                    $orphans[] = [
                        'post_id'   => (int) $post->ID,
                        'title'     => (string) $post->post_title,
                        'permalink' => (string) get_permalink($post),
                        'post_type' => (string) $post->post_type,
                        'modified'  => (string) $post->post_modified_gmt,
                    ];
                }
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
}
