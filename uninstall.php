<?php
/**
 * Uninstall handler. Runs when the user deletes the plugin from wp-admin.
 *
 * Drops both custom tables and removes every option/transient with the cil_ prefix.
 *
 * @package Champlin\InternalLinker
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'cil_embeddings',
    $wpdb->prefix . 'cil_suggestion_log',
];

foreach ($tables as $table) {
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup; table name is a constant identifier ($wpdb->prefix + trusted suffix), not user input; caching is irrelevant when dropping tables.
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}

$options = [
    'cil_db_version',
    'cil_settings',
    'cil_provider',
    'cil_model',
    'cil_threshold',
    'cil_post_types',
    'cil_max_suggestions',
    'cil_api_key',
];

foreach ($options as $option) {
    delete_option($option);
    delete_site_option($option);
}

// Clean up any cil_* transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_cil_') . '%',
        $wpdb->esc_like('_transient_timeout_cil_') . '%'
    )
);

// Unschedule any Action Scheduler jobs.
if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions('cil_index_post');
    as_unschedule_all_actions('cil_bulk_index_batch');
}
