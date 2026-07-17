<?php
/**
 * "Upgrade to Pro" panel rendered inside Free's Settings page.
 *
 * This is a purely informational upsell: pricing cards that link OUT to
 * linkweaver.app, plus a link to the customer account page where Pro is
 * downloaded and installed like any other plugin (Plugins → Add New →
 * Upload Plugin).
 *
 * It deliberately does NOT install, download, or activate the Pro add-on
 * from inside wp-admin. WordPress.org's directory guidelines prohibit a
 * hosted plugin from installing plugins from sources other than
 * WordPress.org, so Free links out and lets the user install the Pro zip
 * themselves through core's standard uploader.
 *
 * @package Champlin\InternalLinker\Admin
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class UpgradeToProPanel
{
    /** Where customers manage their license and download the Pro zip. */
    public const ACCOUNT_URL = 'https://linkweaver.app/';

    public const PRO_PLUGIN_FILE = 'champlin-ai-internal-linker-pro/champlin-ai-internal-linker-pro.php';

    public function render(): void
    {
        $pro_active = $this->is_pro_active();
        ?>
        <div class="chail-card chail-card--accent chail-upgrade-pro">
            <div class="chail-card-header">
                <div>
                    <div class="chail-app-eyebrow">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.39 7.36H22l-6.19 4.5L18.2 21 12 16.5 5.8 21l2.39-7.14L2 9.36h7.61z"/></svg>
                        <?php esc_html_e('Premium', 'champlin-ai-internal-linker'); ?>
                    </div>
                    <h2><?php esc_html_e('Upgrade to LinkWeaver Pro', 'champlin-ai-internal-linker'); ?></h2>
                    <p class="chail-help">
                        <?php esc_html_e('Pro adds hosted AI (no OpenAI key needed), auto-link rules, broken-link checker, bulk URL changer, and Money Pages prioritization. Same plugin author, same architecture, just more.', 'champlin-ai-internal-linker'); ?>
                    </p>
                </div>
                <?php if ($pro_active) : ?>
                    <span class="chail-pill chail-pill-success"><span class="chail-pill-dot"></span><?php esc_html_e('Pro active', 'champlin-ai-internal-linker'); ?></span>
                <?php else : ?>
                    <span class="chail-pill chail-pill-premium"><?php esc_html_e('Available', 'champlin-ai-internal-linker'); ?></span>
                <?php endif; ?>
            </div>
            <div class="chail-card-body">
                <?php if ($pro_active) : ?>
                    <p style="margin:0">
                        <?php
                        printf(
                            /* translators: %s is a link to the Pro license settings */
                            esc_html__('Pro is active on this site. %s', 'champlin-ai-internal-linker'),
                            '<a href="' . esc_url(admin_url('admin.php?page=champlin-ai-internal-linker#license')) . '">' . esc_html__('Manage your license', 'champlin-ai-internal-linker') . '</a>'
                        );
                        ?>
                    </p>
                <?php else : ?>
                    <div class="chail-upgrade-grid">
                        <div class="chail-upgrade-tier">
                            <h3><?php esc_html_e('Premium', 'champlin-ai-internal-linker'); ?></h3>
                            <p class="chail-upgrade-price"><span>$39</span><small>/yr &middot; 1 site</small></p>
                            <ul>
                                <li><?php esc_html_e('Hosted AI (no OpenAI key)', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('Auto-link rules on save', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('Broken-link checker', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('Bulk URL changer', 'champlin-ai-internal-linker'); ?></li>
                            </ul>
                            <a class="chail-btn chail-btn-primary" href="https://linkweaver.app/buy/premium" target="_blank" rel="noopener">
                                <?php esc_html_e('Get Premium', 'champlin-ai-internal-linker'); ?>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </a>
                        </div>
                        <div class="chail-upgrade-tier">
                            <h3><?php esc_html_e('Agency', 'champlin-ai-internal-linker'); ?></h3>
                            <p class="chail-upgrade-price"><span>$149</span><small>/yr &middot; unlimited</small></p>
                            <ul>
                                <li><?php esc_html_e('Everything in Premium', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('Unlimited site activations', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('24h priority support', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('Onboarding call (30 min)', 'champlin-ai-internal-linker'); ?></li>
                            </ul>
                            <a class="chail-btn chail-btn-ghost" href="https://linkweaver.app/buy/agency" target="_blank" rel="noopener">
                                <?php esc_html_e('Get Agency', 'champlin-ai-internal-linker'); ?>
                            </a>
                        </div>
                    </div>

                    <p class="chail-field-hint" style="margin:0">
                        <?php
                        printf(
                            /* translators: %s is a link to the LinkWeaver account page */
                            esc_html__('Already bought Pro? Download it from your %s, then install it under Plugins → Add New → Upload Plugin.', 'champlin-ai-internal-linker'),
                            '<a href="' . esc_url(self::ACCOUNT_URL) . '" target="_blank" rel="noopener">' . esc_html__('LinkWeaver account', 'champlin-ai-internal-linker') . '</a>'
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php
    }

    private function is_pro_active(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active(self::PRO_PLUGIN_FILE);
    }
}
