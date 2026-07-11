<?php
/**
 * Plugin Name:       Champlin AI Internal Linker
 * Plugin URI:        https://linkweaver.app/
 * Description:       Semantic internal-link suggestions powered by embeddings. One-click insert with auto-detected anchor text inside the block editor.
 * Version:           1.3.3
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Champlin Enterprises
 * Author URI:        https://champlinenterprises.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       champlin-ai-internal-linker
 *
 * @package Champlin\InternalLinker
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- "CIL_"/"cil_" is this plugin's established prefix.
define('CIL_VERSION', '1.3.3');
define('CIL_FILE', __FILE__);
define('CIL_DIR', plugin_dir_path(__FILE__));
define('CIL_URL', plugin_dir_url(__FILE__));
define('CIL_SLUG', 'champlin-ai-internal-linker');
define('CIL_DB_VERSION', '1');

$cil_autoload = CIL_DIR . 'vendor/autoload.php';
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if (!file_exists($cil_autoload)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__(
            'Champlin AI Internal Linker: Composer dependencies are missing. Run "composer install" inside the plugin directory.',
            'champlin-ai-internal-linker'
        );
        echo '</p></div>';
    });
    return;
}
require_once $cil_autoload;

/**
 * Auto-update from GitHub releases (REMOVE THIS BLOCK in the WP.org variant via build script).
 *
 * Uses YahnisElsts/plugin-update-checker v5. Polls GitHub releases hourly,
 * surfaces new versions in wp-admin → Updates. No telemetry.
 */
if (is_admin() || (defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI)) {
    $cil_puc = CIL_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
    if (file_exists($cil_puc)) {
        require_once $cil_puc;
        if (class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            $cil_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                'https://github.com/Kevinchamplin/champlin-ai-internal-linker/',
                __FILE__,
                'champlin-ai-internal-linker'
            );
            $cil_vcs_api = $cil_update_checker->getVcsApi();
            if (method_exists($cil_vcs_api, 'enableReleaseAssets')) {
                $cil_vcs_api->enableReleaseAssets();
            }
        }
    }
}

register_activation_hook(__FILE__, [\Champlin\InternalLinker\Plugin::class, 'on_activate']);
register_deactivation_hook(__FILE__, [\Champlin\InternalLinker\Plugin::class, 'on_deactivate']);

add_action('plugins_loaded', static function (): void {
    \Champlin\InternalLinker\Plugin::boot();
});
