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
    define('CIL_SLUG', 'champlin-internal-linker');
}
if (!defined('CIL_DB_VERSION')) {
    define('CIL_DB_VERSION', '1');
}
