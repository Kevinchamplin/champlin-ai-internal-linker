<?php
/**
 * CRUD on the cil_suggestion_log table.
 *
 * @package Champlin\InternalLinker\Storage
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Storage;

use wpdb;

final class SuggestionLog
{
    public function __construct(private wpdb $wpdb)
    {
    }

    public function record_suggestion(int $source_post_id, int $target_post_id, float $similarity): int
    {
        $table = Schema::table_suggestion_log();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $this->wpdb->insert(
            $table,
            [
                'source_post_id' => $source_post_id,
                'target_post_id' => $target_post_id,
                'similarity'     => $similarity,
                'accepted'       => 0,
                'created_at'     => current_time('mysql', true),
            ],
            ['%d', '%d', '%f', '%d', '%s']
        );
        return (int) $this->wpdb->insert_id;
    }

    public function mark_accepted(int $source_post_id, int $target_post_id): void
    {
        $table = Schema::table_suggestion_log();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $this->wpdb->update(
            $table,
            ['accepted' => 1],
            [
                'source_post_id' => $source_post_id,
                'target_post_id' => $target_post_id,
            ],
            ['%d'],
            ['%d', '%d']
        );
    }

    /**
     * Post IDs already linked from a given source (based on accepted suggestions).
     *
     * @return int[]
     */
    public function accepted_targets_for(int $source_post_id): array
    {
        $table = Schema::table_suggestion_log();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name from Schema::table_suggestion_log() is a constant identifier, source_post_id is bound.
        $rows = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT target_post_id FROM {$table} WHERE source_post_id = %d AND accepted = 1",
                $source_post_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
        return array_map('intval', $rows);
    }
}
