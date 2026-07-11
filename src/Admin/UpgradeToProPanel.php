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
    public const ACCOUNT_URL = 'https://linkweaver.app/account';

    public const PRO_PLUGIN_FILE = 'champlin-ai-internal-linker-pro/champlin-ai-internal-linker-pro.php';

    public function render(): void
    {
        $pro_active = $this->is_pro_active();
        ?>
        <div class="cil-card cil-card--accent cil-upgrade-pro">
            <div class="cil-card-header">
                <div>
                    <div class="cil-app-eyebrow">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.39 7.36H22l-6.19 4.5L18.2 21 12 16.5 5.8 21l2.39-7.14L2 9.36h7.61z"/></svg>
                        <?php esc_html_e('Premium', 'champlin-ai-internal-linker'); ?>
                    </div>
                    <h2><?php esc_html_e('Upgrade to LinkWeaver Pro', 'champlin-ai-internal-linker'); ?></h2>
                    <p class="cil-help">
                        <?php esc_html_e('Pro adds hosted AI (no OpenAI key needed), auto-link rules, broken-link checker, bulk URL changer, and Money Pages prioritization. Same plugin author, same architecture, just more.', 'champlin-ai-internal-linker'); ?>
                    </p>
                </div>
                <?php if ($pro_active) : ?>
                    <span class="cil-pill cil-pill-success"><span class="cil-pill-dot"></span><?php esc_html_e('Pro active', 'champlin-ai-internal-linker'); ?></span>
                <?php else : ?>
                    <span class="cil-pill cil-pill-premium"><?php esc_html_e('Available', 'champlin-ai-internal-linker'); ?></span>
                <?php endif; ?>
            </div>
            <div class="cil-card-body">
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
                    <div class="cil-upgrade-grid">
                        <div class="cil-upgrade-tier">
                            <h3><?php esc_html_e('Premium', 'champlin-ai-internal-linker'); ?></h3>
                            <p class="cil-upgrade-price"><span>$39</span><small>/yr &middot; 1 site</small></p>
                            <ul>
                                <li><?php esc_html_e('Hosted AI (no OpenAI key)', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('Auto-link rules on save', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('Broken-link checker', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('Bulk URL changer', 'champlin-ai-internal-linker'); ?></li>
                            </ul>
                            <a class="cil-btn cil-btn-primary" href="https://linkweaver.app/buy/premium" target="_blank" rel="noopener">
                                <?php esc_html_e('Get Premium', 'champlin-ai-internal-linker'); ?>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </a>
                        </div>
                        <div class="cil-upgrade-tier">
                            <h3><?php esc_html_e('Agency', 'champlin-ai-internal-linker'); ?></h3>
                            <p class="cil-upgrade-price"><span>$149</span><small>/yr &middot; unlimited</small></p>
                            <ul>
                                <li><?php esc_html_e('Everything in Premium', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('Unlimited site activations', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('24h priority support', 'champlin-ai-internal-linker'); ?></li>
                                <li><?php esc_html_e('Onboarding call (30 min)', 'champlin-ai-internal-linker'); ?></li>
                            </ul>
                            <a class="cil-btn cil-btn-ghost" href="https://linkweaver.app/buy/agency" target="_blank" rel="noopener">
                                <?php esc_html_e('Get Agency', 'champlin-ai-internal-linker'); ?>
                            </a>
                        </div>
                    </div>

                    <p class="cil-field-hint" style="margin:0">
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

        <style>
            .cil-upgrade-pro h2 { margin-top: 4px; }
            .cil-upgrade-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
            @media (max-width: 720px) { .cil-upgrade-grid { grid-template-columns: 1fr; } }
            .cil-upgrade-tier { border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; padding: 18px; display: flex; flex-direction: column; }
            .cil-upgrade-tier h3 { margin: 0 0 4px; font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600; }
            .cil-upgrade-price { margin: 0 0 12px; font-family: 'Space Grotesk', sans-serif; }
            .cil-upgrade-price span { font-size: 1.875rem; font-weight: 600; letter-spacing: -0.025em; color: #0f172a; }
            .cil-upgrade-price small { color: #64748b; margin-left: 6px; font-size: 0.85rem; }
            .cil-upgrade-tier ul { list-style: none; margin: 0 0 14px; padding: 0; font-size: 0.85rem; color: #475569; line-height: 1.6; flex: 1; }
            .cil-upgrade-tier ul li::before { content: "\2713"; color: #14b8a6; margin-right: 6px; font-weight: 700; }
            .cil-upgrade-tier .cil-btn { align-self: flex-start; }
        </style>
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
