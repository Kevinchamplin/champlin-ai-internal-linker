<?php
/**
 * Insights / ROI report — aggregates what the plugin has actually done for
 * the editor, surfaced as a single dashboard payload.
 *
 * Numbers are computed from existing storage:
 *   - cil_suggestion_log → links delivered / accepted / acceptance rate
 *   - cil_embeddings     → indexed posts, storage bytes, estimated AI cost
 *   - LinkGraphScanner   → orphan count + ratio (live)
 *
 * Time-saved estimate uses the widely-cited 5-minute-per-internal-link
 * baseline (Link Whisper's own marketing claims ~12 hours/week, which
 * implies ~5 min/link at typical editorial throughput).
 *
 * @package Champlin\InternalLinker\Reports
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Reports;

use Champlin\InternalLinker\Storage\Schema;
use wpdb;

final class InsightsReport
{
    public const MINUTES_PER_LINK = 5;
    public const EMBEDDING_COST_PER_POST = 0.00002;
    public const STORAGE_BYTES_PER_POST = 6144;

    public function __construct(
        private wpdb $wpdb,
        private LinkGraphScanner $scanner
    ) {
    }

    /**
     * @return array{
     *   computed_at: string,
     *   links_inserted_total: int,
     *   links_inserted_30d: int,
     *   pages_improved: int,
     *   acceptance_rate: float,
     *   suggestions_delivered: int,
     *   minutes_saved: int,
     *   indexed_posts: int,
     *   estimated_ai_cost: float,
     *   storage_kb: int,
     *   orphan_count: int,
     *   orphan_ratio: float,
     *   top_targets: array<int, array{post_id: int, title: string, permalink: string, inserts: int}>,
     *   recent_activity: array<int, array{source_post_id: int, source_title: string, target_post_id: int, target_title: string, similarity: float, created_at: string}>
     * }
     */
    public function generate(): array
    {
        $log_table = Schema::table_suggestion_log();
        $emb_table = Schema::table_embeddings();

        // Acceptance + counts
        $total_inserted = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$log_table} WHERE accepted = 1");
        $delivered      = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$log_table}");
        $inserted_30d   = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$log_table} WHERE accepted = 1 AND created_at >= %s",
                gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)
            )
        );
        $pages_improved = (int) $this->wpdb->get_var("SELECT COUNT(DISTINCT source_post_id) FROM {$log_table} WHERE accepted = 1");
        $acceptance_rate = $delivered > 0 ? round($total_inserted / $delivered, 4) : 0.0;

        // Indexing stats
        $indexed = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$emb_table}");
        $storage_kb = (int) round(($indexed * self::STORAGE_BYTES_PER_POST) / 1024);
        $estimated_cost = round($indexed * self::EMBEDDING_COST_PER_POST, 4);

        // Orphan graph (cached scan). Ratio is against total eligible published
        // posts (not just indexed) — orphans on un-indexed posts still count
        // toward the SEO problem we're measuring.
        $graph = $this->scanner->snapshot(false);
        $orphan_stats = $this->compute_orphan_stats($graph);
        $orphan_count = $orphan_stats['orphans'];
        $orphan_total = $orphan_stats['total'];
        $orphan_ratio = $orphan_total > 0 ? round($orphan_count / $orphan_total, 4) : 0.0;

        // Top 10 targets — posts receiving the most accepted inbound links from this plugin.
        $top_targets_rows = $this->wpdb->get_results(
            "SELECT target_post_id, COUNT(*) AS inserts
             FROM {$log_table}
             WHERE accepted = 1
             GROUP BY target_post_id
             ORDER BY inserts DESC, target_post_id DESC
             LIMIT 10",
            ARRAY_A
        );
        $top_targets = [];
        foreach ((array) $top_targets_rows as $r) {
            $post = get_post((int) $r['target_post_id']);
            if (!$post) {
                continue;
            }
            $top_targets[] = [
                'post_id'   => (int) $r['target_post_id'],
                'title'     => (string) $post->post_title,
                'permalink' => (string) get_permalink($post),
                'inserts'   => (int) $r['inserts'],
            ];
        }

        // Recent 10 accepted inserts.
        $recent_rows = $this->wpdb->get_results(
            "SELECT source_post_id, target_post_id, similarity, created_at
             FROM {$log_table}
             WHERE accepted = 1
             ORDER BY created_at DESC, id DESC
             LIMIT 10",
            ARRAY_A
        );
        $recent_activity = [];
        foreach ((array) $recent_rows as $r) {
            $src = get_post((int) $r['source_post_id']);
            $tgt = get_post((int) $r['target_post_id']);
            if (!$src || !$tgt) {
                continue;
            }
            $recent_activity[] = [
                'source_post_id' => (int) $r['source_post_id'],
                'source_title'   => (string) $src->post_title,
                'target_post_id' => (int) $r['target_post_id'],
                'target_title'   => (string) $tgt->post_title,
                'similarity'     => (float) $r['similarity'],
                'created_at'     => (string) $r['created_at'],
            ];
        }

        return [
            'computed_at'           => gmdate('Y-m-d H:i:s'),
            'links_inserted_total'  => $total_inserted,
            'links_inserted_30d'    => $inserted_30d,
            'pages_improved'        => $pages_improved,
            'acceptance_rate'       => $acceptance_rate,
            'suggestions_delivered' => $delivered,
            'minutes_saved'         => $total_inserted * self::MINUTES_PER_LINK,
            'indexed_posts'         => $indexed,
            'estimated_ai_cost'     => $estimated_cost,
            'storage_kb'            => $storage_kb,
            'orphan_count'          => $orphan_count,
            'orphan_ratio'          => $orphan_ratio,
            'top_targets'           => $top_targets,
            'recent_activity'       => $recent_activity,
        ];
    }

    /**
     * @param array{inbound_counts: array<int, int>} $graph
     * @return array{orphans: int, total: int}
     */
    private function compute_orphan_stats(array $graph): array
    {
        $inbound = (array) ($graph['inbound_counts'] ?? []);
        $post_types = \Champlin\InternalLinker\Embeddings\ProviderFactory::settings()['post_types'] ?? ['post'];

        $total   = 0;
        $orphans = 0;
        $paged   = 1;
        do {
            $page_query = new \WP_Query([
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'paged'          => $paged,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);
            if ($page_query->posts === []) {
                break;
            }
            foreach ($page_query->posts as $id) {
                $total++;
                if (((int) ($inbound[$id] ?? 0)) === 0) {
                    $orphans++;
                }
            }
            $paged++;
        } while (count($page_query->posts) === 200);

        return ['orphans' => $orphans, 'total' => $total];
    }
}
