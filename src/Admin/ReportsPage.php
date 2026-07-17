<?php
/**
 * Reports admin screen (Tools → AI Internal Linker → Reports).
 *
 * Renders the orphan-page report — every published post that has zero
 * inbound internal links pointing to it.
 *
 * @package Champlin\InternalLinker\Admin
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Admin;

use Champlin\InternalLinker\Reports\OrphanReport;

final class ReportsPage
{
    public const MENU_SLUG = 'champlin-internal-linker-reports';

    public function __construct(private OrphanReport $orphan_report)
    {
    }

    public function register(): void
    {
        add_submenu_page(
            SettingsPage::MENU_SLUG,
            __('Reports', 'champlin-ai-internal-linker'),
            __('Reports', 'champlin-ai-internal-linker'),
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

        $force  = isset($_GET['rescan']) && check_admin_referer('chail_rescan_orphans', '_chailnonce');
        $report = $this->orphan_report->generate((bool) $force);

        wp_enqueue_script(
            'chail-reports',
            CHAIL_URL . 'assets/admin/reports.js',
            ['wp-api-fetch'],
            CHAIL_VERSION,
            true
        );
        wp_localize_script('chail-reports', 'chailReports', [
            'nonce' => wp_create_nonce('wp_rest'),
            'rest'  => esc_url_raw(rest_url('chail/v1/reports')),
        ]);

        $css = CHAIL_DIR . 'assets/dist/admin/admin.css';
        if (file_exists($css)) {
            wp_enqueue_style(
                'chail-admin',
                CHAIL_URL . 'assets/dist/admin/admin.css',
                [],
                (string) filemtime($css)
            );
        }

        require CHAIL_DIR . 'includes/views/reports.php';
    }
}
