<?php
/**
 * Bulk re-index a site by paginating through published posts and enqueuing
 * Action Scheduler jobs in fixed-size batches.
 *
 * Progress is tracked in the cil_bulk_progress option so the admin UI can
 * poll a REST endpoint.
 *
 * @package Champlin\InternalLinker\Indexing
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Indexing;

use Champlin\InternalLinker\Embeddings\ProviderFactory;

final class BulkIndexer
{
    public const HOOK         = 'cil_bulk_index_batch';
    public const OPTION_STATE = 'cil_bulk_progress';
    public const BATCH_SIZE   = 25;

    public function __construct(private IndexQueue $index_queue)
    {
    }

    /**
     * Begin a fresh bulk re-index. Replaces any prior queued batches.
     *
     * @return array{total: int, processed: int, started_at: string, status: string}
     */
    public function start(): array
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::HOOK);
        }

        $post_types = ProviderFactory::settings()['post_types'] ?? ['post'];
        $total      = $this->count_eligible($post_types);

        $state = [
            'total'      => $total,
            'processed'  => 0,
            'started_at' => gmdate('Y-m-d H:i:s'),
            'status'     => $total === 0 ? 'complete' : 'running',
        ];
        update_option(self::OPTION_STATE, $state, false);

        if ($total === 0) {
            return $state;
        }

        // Enqueue the first batch; each batch enqueues the next when it finishes.
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::HOOK, ['offset' => 0], 'champlin-ai-internal-linker');
        } else {
            $this->run_batch(0);
        }

        return $state;
    }

    /**
     * @return array{total: int, processed: int, started_at: string, status: string}
     */
    public function progress(): array
    {
        $state = get_option(self::OPTION_STATE, []);
        if (!is_array($state)) {
            $state = [];
        }
        return [
            'total'      => (int) ($state['total'] ?? 0),
            'processed'  => (int) ($state['processed'] ?? 0),
            'started_at' => (string) ($state['started_at'] ?? ''),
            'status'     => (string) ($state['status'] ?? 'idle'),
        ];
    }

    /**
     * Action Scheduler entry point — process one batch and schedule the next.
     */
    public function run_batch(int $offset): void
    {
        $post_types = ProviderFactory::settings()['post_types'] ?? ['post'];
        $ids        = $this->fetch_batch($post_types, $offset, self::BATCH_SIZE);

        foreach ($ids as $post_id) {
            $this->index_queue->enqueue($post_id);
        }

        $state              = $this->progress();
        $state['processed'] = min($state['total'], $state['processed'] + count($ids));
        $state['status']    = $state['processed'] >= $state['total'] ? 'complete' : 'running';
        update_option(self::OPTION_STATE, $state, false);

        if ($state['status'] === 'running' && function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(
                self::HOOK,
                ['offset' => $offset + self::BATCH_SIZE],
                'champlin-ai-internal-linker'
            );
        }
    }

    /**
     * @param string[] $post_types
     */
    private function count_eligible(array $post_types): int
    {
        $query = new \WP_Query([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ]);
        return (int) $query->found_posts;
    }

    /**
     * @param string[] $post_types
     * @return int[]
     */
    private function fetch_batch(array $post_types, int $offset, int $limit): array
    {
        $query = new \WP_Query([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        return array_map('intval', $query->posts);
    }
}
