<?php
/**
 * Versioned schema migrations via dbDelta.
 *
 * @package Champlin\InternalLinker\Storage
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Storage;

final class Schema
{
    public const OPTION_DB_VERSION = 'cil_db_version';

    public static function install(): void
    {
        self::run_migrations();
        update_option(self::OPTION_DB_VERSION, CIL_DB_VERSION, false);
    }

    public static function maybe_upgrade(): void
    {
        $current = get_option(self::OPTION_DB_VERSION, '0');
        if (version_compare((string) $current, CIL_DB_VERSION, '<')) {
            self::run_migrations();
            update_option(self::OPTION_DB_VERSION, CIL_DB_VERSION, false);
        }
    }

    public static function table_embeddings(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cil_embeddings';
    }

    public static function table_suggestion_log(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cil_suggestion_log';
    }

    private static function run_migrations(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $embeddings      = self::table_embeddings();
        $suggestion_log  = self::table_suggestion_log();

        // dbDelta is intentionally formatting-sensitive — keep two spaces after PRIMARY KEY,
        // no backticks on column names, and a single space between column name and type.
        $sql_embeddings = "CREATE TABLE {$embeddings} (
            post_id bigint(20) unsigned NOT NULL,
            embedding longblob NOT NULL,
            model varchar(64) NOT NULL,
            dimensions smallint(5) unsigned NOT NULL,
            content_hash char(64) NOT NULL,
            indexed_at datetime NOT NULL,
            PRIMARY KEY  (post_id),
            KEY model_dim (model, dimensions)
        ) {$charset_collate};";

        $sql_suggestion_log = "CREATE TABLE {$suggestion_log} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_post_id bigint(20) unsigned NOT NULL,
            target_post_id bigint(20) unsigned NOT NULL,
            similarity float NOT NULL,
            accepted tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY source (source_post_id),
            KEY accepted (accepted)
        ) {$charset_collate};";

        dbDelta($sql_embeddings);
        dbDelta($sql_suggestion_log);
    }
}
