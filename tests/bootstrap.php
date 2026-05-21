<?php
/**
 * PHPUnit bootstrap. Uses Brain Monkey to stub WordPress functions so tests
 * can exercise pure-PHP logic (normalizer, cosine, anchor extraction) without
 * a WordPress install.
 *
 * @package Champlin\InternalLinker\Tests
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('CIL_VERSION')) {
    define('CIL_VERSION', '1.0.0');
}
if (!defined('CIL_FILE')) {
    define('CIL_FILE', dirname(__DIR__) . '/champlin-ai-internal-linker.php');
}
if (!defined('CIL_DIR')) {
    define('CIL_DIR', dirname(__DIR__) . '/');
}
if (!defined('CIL_URL')) {
    define('CIL_URL', 'https://example.test/wp-content/plugins/champlin-ai-internal-linker/');
}
if (!defined('CIL_SLUG')) {
    define('CIL_SLUG', 'champlin-ai-internal-linker');
}
if (!defined('CIL_DB_VERSION')) {
    define('CIL_DB_VERSION', '1');
}

// WordPress time constants used by Reports/* code paths.
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 60 * 60);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 60 * 60 * 24);
}

// Minimal WP function stubs in the global namespace, declared once at boot.
if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1)
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return false;
        }
        if ($component === PHP_URL_HOST) {
            return $parsed['host'] ?? '';
        }
        return $parsed;
    }
}
if (!function_exists('url_to_postid')) {
    function url_to_postid(string $url): int
    {
        if (preg_match('#/post-(\d+)(?:/|$|\?)#', $url, $m)) {
            return (int) $m[1];
        }
        if (preg_match('#[?&]p=(\d+)#', $url, $m)) {
            return (int) $m[1];
        }
        return 0;
    }
}
