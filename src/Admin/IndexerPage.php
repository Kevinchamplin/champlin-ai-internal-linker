<?php
/**
 * Bulk re-indexer screen (Tools → AI Internal Linker → Re-index).
 *
 * @package Champlin\InternalLinker\Admin
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Admin;

use Champlin\InternalLinker\Indexing\BulkIndexer;

final class IndexerPage
{
    public const MENU_SLUG = 'champlin-internal-linker-indexer';

    public function __construct(private BulkIndexer $bulk_indexer)
    {
    }

    public function register(): void
    {
        add_submenu_page(
            SettingsPage::MENU_SLUG,
            __('Re-index', 'champlin-ai-internal-linker'),
            __('Re-index', 'champlin-ai-internal-linker'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Administrator capability required.', 'champlin-ai-internal-linker'));
        }

        $progress = $this->bulk_indexer->progress();

        wp_enqueue_script(
            'cil-indexer',
            CIL_URL . 'assets/admin/indexer.js',
            ['wp-api-fetch'],
            CIL_VERSION,
            true
        );
        wp_localize_script('cil-indexer', 'cilIndexer', [
            'nonce'      => wp_create_nonce('wp_rest'),
            'rest'       => esc_url_raw(rest_url('cil/v1/index')),
            'i18nStart'  => __('Start re-index', 'champlin-ai-internal-linker'),
            'i18nPause'  => __('Re-indexing in progress…', 'champlin-ai-internal-linker'),
            'i18nDone'   => __('Complete', 'champlin-ai-internal-linker'),
        ]);

        $css = CIL_DIR . 'assets/dist/admin/admin.css';
        if (file_exists($css)) {
            wp_enqueue_style(
                'cil-admin',
                CIL_URL . 'assets/dist/admin/admin.css',
                [],
                (string) filemtime($css)
            );
        }

        require CIL_DIR . 'includes/views/indexer.php';
    }
}
