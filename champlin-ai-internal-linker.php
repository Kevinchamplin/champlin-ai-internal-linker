<?php
/**
 * Plugin Name:       Champlin AI Internal Linker
 * Plugin URI:        https://linkweaver.app/
 * Description:       Semantic internal-link suggestions powered by embeddings. One-click insert with auto-detected anchor text inside the block editor.
 * Version:           1.3.4
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

define('CHAIL_VERSION', '1.3.4');
define('CHAIL_FILE', __FILE__);
define('CHAIL_DIR', plugin_dir_path(__FILE__));
define('CHAIL_URL', plugin_dir_url(__FILE__));
define('CHAIL_SLUG', 'champlin-ai-internal-linker');
define('CHAIL_DB_VERSION', '1');

$chail_autoload = CHAIL_DIR . 'vendor/autoload.php';
if (!file_exists($chail_autoload)) {
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
require_once $chail_autoload;

/**
 * Auto-update from GitHub releases (REMOVE THIS BLOCK in the WP.org variant via build script).
 *
 * Uses YahnisElsts/plugin-update-checker v5. Polls GitHub releases hourly,
 * surfaces new versions in wp-admin → Updates. No telemetry.
 */
if (is_admin() || (defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI)) {
    $chail_puc = CHAIL_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
    if (file_exists($chail_puc)) {
        require_once $chail_puc;
        if (class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            $chail_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                'https://github.com/Kevinchamplin/champlin-ai-internal-linker/',
                __FILE__,
                'champlin-ai-internal-linker'
            );
            $chail_vcs_api = $chail_update_checker->getVcsApi();
            if (method_exists($chail_vcs_api, 'enableReleaseAssets')) {
                $chail_vcs_api->enableReleaseAssets();
            }
        }
    }
}

register_activation_hook(__FILE__, [\Champlin\InternalLinker\Plugin::class, 'on_activate']);
register_deactivation_hook(__FILE__, [\Champlin\InternalLinker\Plugin::class, 'on_deactivate']);

add_action('plugins_loaded', static function (): void {
    \Champlin\InternalLinker\Plugin::boot();
});
