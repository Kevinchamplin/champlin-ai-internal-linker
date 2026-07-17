<?php
/**
 * Uninstall handler. Runs when the user deletes the plugin from wp-admin.
 *
 * Drops both custom tables and removes every option/transient with the chail_ prefix.
 *
 * @package Champlin\InternalLinker
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall runs in global scope; these are this plugin's own cleanup vars ("chail_" is its established prefix).
$chail_tables = [
    $wpdb->prefix . 'chail_embeddings',
    $wpdb->prefix . 'chail_suggestion_log',
];

foreach ($chail_tables as $chail_table) {
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup; table name is a constant identifier ($wpdb->prefix + trusted suffix), not user input; caching is irrelevant when dropping tables.
    $wpdb->query("DROP TABLE IF EXISTS `{$chail_table}`");
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}

$chail_options = [
    'chail_db_version',
    'chail_settings',
    'chail_provider',
    'chail_model',
    'chail_threshold',
    'chail_post_types',
    'chail_max_suggestions',
    'chail_api_key',
];

foreach ($chail_options as $chail_option) {
    delete_option($chail_option);
    delete_site_option($chail_option);
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Clean up any chail_* transients.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup of this plugin's own transients; caching is irrelevant during uninstall.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_chail_') . '%',
        $wpdb->esc_like('_transient_timeout_chail_') . '%'
    )
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

// Unschedule any Action Scheduler jobs.
if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions('chail_index_post');
    as_unschedule_all_actions('chail_bulk_index_batch');
}
