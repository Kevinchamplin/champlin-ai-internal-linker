<?php
/**
 * Insights admin sub-page (Tools → AI Linker → Insights).
 *
 * Shows the editorial-ROI view of the plugin: links delivered, pages improved,
 * time saved, OpenAI cost, top-linked targets, recent activity.
 *
 * @package Champlin\InternalLinker\Admin
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Admin;

use Champlin\InternalLinker\Reports\InsightsReport;

final class InsightsPage
{
    public const MENU_SLUG = 'champlin-internal-linker-insights';

    public function __construct(private InsightsReport $report)
    {
    }

    public function register(): void
    {
        add_submenu_page(
            SettingsPage::MENU_SLUG,
            __('Insights', 'champlin-ai-internal-linker'),
            __('Insights', 'champlin-ai-internal-linker'),
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

        // CSV export branch — checked before the view renders to avoid HTML preamble.
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $nonce = isset($_GET['_cilnonce']) ? sanitize_text_field(wp_unslash((string) $_GET['_cilnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'cil_insights_csv')) {
                wp_die(esc_html__('Invalid or expired security token.', 'champlin-ai-internal-linker'));
            }
            nocache_headers();
            $filename = sprintf('cil-activity-%s.csv', gmdate('Ymd-His'));
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $this->report->stream_activity_csv();
            exit;
        }

        $insights = $this->report->generate();

        $css = CIL_DIR . 'assets/dist/admin/admin.css';
        if (file_exists($css)) {
            wp_enqueue_style(
                'cil-admin',
                CIL_URL . 'assets/dist/admin/admin.css',
                [],
                (string) filemtime($css)
            );
        }

        require CIL_DIR . 'includes/views/insights.php';
    }
}
