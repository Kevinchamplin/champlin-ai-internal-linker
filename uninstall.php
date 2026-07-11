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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall runs in global scope; these are this plugin's own cleanup vars ("cil_" is its established prefix).
$cil_tables = [
    $wpdb->prefix . 'cil_embeddings',
    $wpdb->prefix . 'cil_suggestion_log',
];

foreach ($cil_tables as $cil_table) {
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup; table name is a constant identifier ($wpdb->prefix + trusted suffix), not user input; caching is irrelevant when dropping tables.
    $wpdb->query("DROP TABLE IF EXISTS `{$cil_table}`");
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}

$cil_options = [
    'cil_db_version',
    'cil_settings',
    'cil_provider',
    'cil_model',
    'cil_threshold',
    'cil_post_types',
    'cil_max_suggestions',
    'cil_api_key',
];

foreach ($cil_options as $cil_option) {
    delete_option($cil_option);
    delete_site_option($cil_option);
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Clean up any cil_* transients.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup of this plugin's own transients; caching is irrelevant during uninstall.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_cil_') . '%',
        $wpdb->esc_like('_transient_timeout_cil_') . '%'
    )
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

// Unschedule any Action Scheduler jobs.
if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions('cil_index_post');
    as_unschedule_all_actions('cil_bulk_index_batch');
}
