<?php
/**
 * "Upgrade to Pro" panel rendered inside Free's Settings page.
 *
 * Two states:
 *   - Pro NOT installed -> show pricing cards (links out to linkweaver.app)
 *     plus an "Already have a license?" form. Submitting the form makes
 *     Free fetch the Pro zip from linker-api and install + activate it.
 *   - Pro IS installed  -> show "Pro is active" confirmation.
 *
 * The install action is gated on `current_user_can('install_plugins')`,
 * a nonce, and a license validation against linker-api. The download URL
 * is locked to the linker-api host so the user can't be tricked into
 * pulling arbitrary zips.
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
    public const ACTION         = 'cil_install_pro';
    public const NONCE_FIELD    = '_cil_install_pro_nonce';

    public const LICENSE_API    = 'https://linker-api.champlinenterprises.com/api/license/validate';
    public const UPDATES_API    = 'https://linker-api.champlinenterprises.com/api/updates/metadata';
    public const TRUSTED_HOST   = 'linker-api.champlinenterprises.com';

    public const PRO_PLUGIN_FILE = 'champlin-ai-internal-linker-pro/champlin-ai-internal-linker-pro.php';

    public function render(): void
    {
        $pro_active = $this->is_pro_active();

        $notice = isset($_GET['cil_pro_install']) ? sanitize_key((string) $_GET['cil_pro_install']) : '';
        $notice_msg = isset($_GET['cil_pro_msg']) ? sanitize_text_field(wp_unslash((string) $_GET['cil_pro_msg'])) : '';

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
                <?php if ($notice === 'success') : ?>
                    <div class="cil-banner cil-banner-info" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <p><span class="cil-banner-title"><?php esc_html_e('Pro installed and activated.', 'champlin-ai-internal-linker'); ?></span> <?php esc_html_e('Hosted AI is now serving your suggestions. No OpenAI key required.', 'champlin-ai-internal-linker'); ?></p>
                    </div>
                <?php elseif ($notice === 'error') : ?>
                    <div class="cil-banner cil-banner-info" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <p>
                            <span class="cil-banner-title"><?php esc_html_e('Install failed.', 'champlin-ai-internal-linker'); ?></span>
                            <?php echo $notice_msg !== '' ? esc_html($notice_msg) : esc_html__('Check your license key and try again, or email kevin@kevinchamplin.com.', 'champlin-ai-internal-linker'); ?>
                        </p>
                    </div>
                <?php endif; ?>

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

                    <details class="cil-upgrade-install">
                        <summary>
                            <strong><?php esc_html_e('Already bought Pro?', 'champlin-ai-internal-linker'); ?></strong>
                            <span><?php esc_html_e('Paste your license key to install Pro right here, no zip uploads needed.', 'champlin-ai-internal-linker'); ?></span>
                        </summary>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cil-upgrade-form">
                            <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                            <?php wp_nonce_field(self::ACTION, self::NONCE_FIELD); ?>
                            <label class="cil-field-label" for="cil-pro-license-key">
                                <?php esc_html_e('License key', 'champlin-ai-internal-linker'); ?>
                            </label>
                            <div class="cil-upgrade-form-row">
                                <input
                                    type="text"
                                    id="cil-pro-license-key"
                                    name="license_key"
                                    class="cil-input cil-input--mono"
                                    placeholder="cli_live_premium_..."
                                    autocomplete="off"
                                    spellcheck="false"
                                    required>
                                <button type="submit" class="cil-btn cil-btn-primary" <?php disabled(!current_user_can('install_plugins')); ?>>
                                    <?php esc_html_e('Install Pro', 'champlin-ai-internal-linker'); ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                </button>
                            </div>
                            <p class="cil-field-hint">
                                <?php
                                printf(
                                    /* translators: %s is the linker-api domain */
                                    esc_html__('Free downloads the Pro plugin from %s using your license key, then installs it like any other plugin. We do not transmit any of your content. Read more about the connection at the bottom of this page.', 'champlin-ai-internal-linker'),
                                    '<code>' . esc_html(self::TRUSTED_HOST) . '</code>'
                                );
                                ?>
                            </p>
                        </form>
                    </details>
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
            .cil-upgrade-install { border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; background: #f8fafc; }
            .cil-upgrade-install summary { cursor: pointer; list-style: none; }
            .cil-upgrade-install summary::-webkit-details-marker { display: none; }
            .cil-upgrade-install summary strong { display: inline; }
            .cil-upgrade-install summary span { display: block; font-size: 0.8rem; color: #64748b; margin-top: 2px; }
            .cil-upgrade-form { margin-top: 12px; }
            .cil-upgrade-form-row { display: flex; gap: 8px; align-items: stretch; max-width: 720px; }
            .cil-upgrade-form-row .cil-input { flex: 1; }
            .cil-upgrade-form-row .cil-btn { flex-shrink: 0; }
        </style>
        <?php
    }

    public function handle_install(): void
    {
        if (!current_user_can('install_plugins')) {
            wp_die(esc_html__('You do not have permission to install plugins.', 'champlin-ai-internal-linker'), '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION, self::NONCE_FIELD);

        $key = isset($_POST['license_key'])
            ? sanitize_text_field(wp_unslash((string) $_POST['license_key']))
            : '';
        if ($key === '') {
            $this->redirect_with('error', __('No license key was provided.', 'champlin-ai-internal-linker'));
        }

        $site = home_url();

        // 1) Validate license against linker-api
        $validate = wp_remote_post(self::LICENSE_API, [
            'timeout' => 12,
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['license' => $key, 'slug' => 'champlin-ai-internal-linker-pro', 'site' => $site]),
        ]);
        if (is_wp_error($validate)) {
            $this->redirect_with('error', __('Could not reach the LinkWeaver license server. Check your connection and try again.', 'champlin-ai-internal-linker'));
        }
        $code = (int) wp_remote_retrieve_response_code($validate);
        $body = json_decode((string) wp_remote_retrieve_body($validate), true);
        if (!is_array($body)) {
            $this->redirect_with('error', __('License server returned an unexpected response.', 'champlin-ai-internal-linker'));
        }
        if ($code !== 200 || empty($body['active'])) {
            $msg = isset($body['message']) && is_string($body['message'])
                ? $body['message']
                : __('License key is invalid or expired.', 'champlin-ai-internal-linker');
            $this->redirect_with('error', $msg);
        }

        // 2) Get download URL from metadata endpoint
        $meta_url = add_query_arg([
            'license' => $key,
            'slug'    => 'champlin-ai-internal-linker-pro',
            'site'    => $site,
        ], self::UPDATES_API);
        $meta = wp_remote_get($meta_url, ['timeout' => 12, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($meta)) {
            $this->redirect_with('error', __('Could not fetch the Pro download URL. Try again in a moment.', 'champlin-ai-internal-linker'));
        }
        $meta_body = json_decode((string) wp_remote_retrieve_body($meta), true);
        $download_url = is_array($meta_body) && isset($meta_body['download_url'])
            ? (string) $meta_body['download_url']
            : '';
        if ($download_url === '' || !$this->is_trusted_url($download_url)) {
            $this->redirect_with('error', __('The download URL from the license server was missing or untrusted.', 'champlin-ai-internal-linker'));
        }

        // 3) Install + activate via WordPress's stock Plugin_Upgrader.
        // Plugin_Upgrader initializes the filesystem internally; no extra setup needed here.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $skin = new \Automatic_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->install($download_url, ['overwrite_package' => true]);

        if (is_wp_error($result)) {
            $this->redirect_with('error', $result->get_error_message());
        }
        if ($result === false || $result === null) {
            $skin_errors = $skin->get_error_messages();
            $msg = $skin_errors !== [] ? (string) reset($skin_errors) : __('Install failed for an unknown reason.', 'champlin-ai-internal-linker');
            $this->redirect_with('error', $msg);
        }

        // Persist the license key BEFORE activating so Pro picks it up on first run.
        update_option('cilp_license_key', $key, false);

        // Activate Pro
        $activate = activate_plugin(self::PRO_PLUGIN_FILE);
        if (is_wp_error($activate)) {
            $this->redirect_with('error', $activate->get_error_message());
        }

        $this->redirect_with('success', '');
    }

    private function is_pro_active(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active(self::PRO_PLUGIN_FILE);
    }

    private function is_trusted_url(string $url): bool
    {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }
        return strtolower((string) $parts['scheme']) === 'https'
            && strtolower((string) $parts['host']) === self::TRUSTED_HOST;
    }

    private function redirect_with(string $status, string $message): void
    {
        $url = add_query_arg([
            'page'             => SettingsPage::MENU_SLUG,
            'cil_pro_install'  => $status,
            'cil_pro_msg'      => rawurlencode($message),
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
