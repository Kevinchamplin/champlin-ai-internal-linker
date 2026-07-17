<?php
/**
 * CRUD on the chail_embeddings table.
 *
 * Embeddings are stored as packed float32 BLOBs to keep row size predictable
 * (~6KB for the default 1536-dim text-embedding-3-small model).
 *
 * @package Champlin\InternalLinker\Storage
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Storage;

use wpdb;

final class VectorStore
{
    public function __construct(private wpdb $wpdb)
    {
    }

    /**
     * Upsert an embedding for a post.
     *
     * @param int      $post_id      Post ID.
     * @param float[]  $vector       Embedding vector.
     * @param string   $model        Model identifier (e.g. text-embedding-3-small).
     * @param string   $content_hash SHA-256 of the normalized content used to compute the embedding.
     */
    public function upsert(int $post_id, array $vector, string $model, string $content_hash): bool
    {
        $table = Schema::table_embeddings();
        $blob  = self::pack_vector($vector);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $this->wpdb->replace(
            $table,
            [
                'post_id'      => $post_id,
                'embedding'    => $blob,
                'model'        => $model,
                'dimensions'   => count($vector),
                'content_hash' => $content_hash,
                'indexed_at'   => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * @return array{vector: float[], model: string, dimensions: int, content_hash: string, indexed_at: string}|null
     */
    public function get(int $post_id): ?array
    {
        $table = Schema::table_embeddings();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/column identifiers are constants (Schema::table_*(), $wpdb->posts/$wpdb->users), never user input; bound values use %s/%d placeholders.
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT embedding, model, dimensions, content_hash, indexed_at FROM {$table} WHERE post_id = %d",
                $post_id
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$row) {
            return null;
        }

        return [
            'vector'       => self::unpack_vector((string) $row['embedding']),
            'model'        => (string) $row['model'],
            'dimensions'   => (int) $row['dimensions'],
            'content_hash' => (string) $row['content_hash'],
            'indexed_at'   => (string) $row['indexed_at'],
        ];
    }

    /**
     * Return the stored content_hash for a post (or null if not indexed).
     */
    public function content_hash(int $post_id): ?string
    {
        $table = Schema::table_embeddings();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/column identifiers are constants (Schema::table_*(), $wpdb->posts/$wpdb->users), never user input; bound values use %s/%d placeholders.
        $hash = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT content_hash FROM {$table} WHERE post_id = %d",
                $post_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $hash === null ? null : (string) $hash;
    }

    /**
     * Fetch candidate vectors for ranking. Returns an iterator of [post_id, vector] pairs.
     *
     * @param int[]  $excluded_post_ids Post IDs to skip (source + already-linked targets).
     * @param string $model             Limit to embeddings produced by this model.
     * @param int    $batch_size        Server-side batch size for paging.
     * @return \Generator<int, array{post_id: int, vector: float[]}>
     */
    public function iterate_candidates(array $excluded_post_ids, string $model, int $batch_size = 200): \Generator
    {
        $table   = Schema::table_embeddings();
        $exclude = array_values(array_filter(array_map('intval', $excluded_post_ids), static fn(int $id): bool => $id > 0));

        $where_excluded = '';
        if ($exclude !== []) {
            $placeholders   = implode(',', array_fill(0, count($exclude), '%d'));
            $where_excluded = " AND post_id NOT IN ({$placeholders})";
        }

        $offset = 0;
        do {
            // Placeholder order matches the SQL string below: model, then exclude IDs, then limit/offset.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- table/column identifiers are constants (Schema::table_*(), $wpdb->posts/$wpdb->users), never user input; bound values use %s/%d placeholders; replacements are passed via array_merge() spread which PHPCS can't statically count.
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT post_id, embedding FROM {$table} WHERE model = %s{$where_excluded} ORDER BY post_id LIMIT %d OFFSET %d",
                    ...array_merge([$model], $exclude, [$batch_size, $offset])
                ),
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

            if (!$rows) {
                break;
            }
            foreach ($rows as $row) {
                yield [
                    'post_id' => (int) $row['post_id'],
                    'vector'  => self::unpack_vector((string) $row['embedding']),
                ];
            }
            $offset += count($rows);
        } while (count($rows) === $batch_size);
    }

    public function delete(int $post_id): bool
    {
        $table = Schema::table_embeddings();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (bool) $this->wpdb->delete($table, ['post_id' => $post_id], ['%d']);
    }

    public function count_indexed(): int
    {
        $table = Schema::table_embeddings();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/column identifiers are constants (Schema::table_*(), $wpdb->posts/$wpdb->users), never user input; bound values use %s/%d placeholders.
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * @param float[] $vector
     */
    public static function pack_vector(array $vector): string
    {
        return pack('f*', ...array_map(static fn($v): float => (float) $v, $vector));
    }

    /**
     * @return float[]
     */
    public static function unpack_vector(string $blob): array
    {
        if ($blob === '') {
            return [];
        }
        $unpacked = unpack('f*', $blob);
        return $unpacked === false ? [] : array_values($unpacked);
    }
}
