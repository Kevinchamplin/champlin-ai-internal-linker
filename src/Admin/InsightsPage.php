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
            __('Insights', 'champlin-internal-linker'),
            __('Insights', 'champlin-internal-linker'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Administrator capability required.', 'champlin-internal-linker'));
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
