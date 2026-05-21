<?php
/**
 * Settings page view.
 *
 * @var array $settings   Current settings (sanitized).
 * @var \WP_Post_Type[] $post_types Public post type objects.
 *
 * @package Champlin\InternalLinker\Admin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$has_key = !empty($settings['api_key']);
$threshold_label = (string) $settings['threshold'];
$cil_version = defined('CIL_VERSION') ? CIL_VERSION : '';

/**
 * Allow Pro (or other add-ons) to override the provider summary when an
 * external provider is active — e.g. "Hosted AI via Champlin Enterprises".
 *
 * @param array $summary { active: bool, label: string, hint: string }
 */
$provider_summary = (array) apply_filters('cil_provider_summary', [
    'active' => false,
    'label'  => '',
    'hint'   => '',
], $settings);
$using_hosted_ai = !empty($provider_summary['active']);
?>
<div class="wrap cil-wrap">
    <div class="cil-app">

        <header class="cil-app-header">
            <div class="cil-app-title">
                <div class="cil-app-eyebrow">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    AI Linker · Settings
                </div>
                <h1><?php esc_html_e('Connect your AI, tune your suggestions.', 'champlin-ai-internal-linker'); ?></h1>
                <p class="cil-app-subtitle"><?php esc_html_e('These settings control how the plugin finds and ranks semantically related posts. Saved settings apply immediately — no rebuild required.', 'champlin-ai-internal-linker'); ?></p>
            </div>
            <div class="cil-app-actions">
                <?php if ($using_hosted_ai) : ?>
                    <span class="cil-pill cil-pill-premium" title="<?php echo esc_attr($provider_summary['hint'] ?? ''); ?>">
                        <span class="cil-pill-dot"></span>
                        <?php echo esc_html($provider_summary['label']); ?>
                    </span>
                <?php elseif ($has_key) : ?>
                    <span class="cil-pill cil-pill-success" title="<?php esc_attr_e('OpenAI API key configured', 'champlin-ai-internal-linker'); ?>">
                        <span class="cil-pill-dot"></span> <?php esc_html_e('Connected', 'champlin-ai-internal-linker'); ?>
                    </span>
                <?php else : ?>
                    <span class="cil-pill cil-pill-warning" title="<?php esc_attr_e('Configure API key to enable suggestions', 'champlin-ai-internal-linker'); ?>">
                        <span class="cil-pill-dot"></span> <?php esc_html_e('Not connected', 'champlin-ai-internal-linker'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </header>

        <?php settings_errors('cil_settings_group'); ?>

        <?php if (!$has_key && !$using_hosted_ai) : ?>
            <div class="cil-banner cil-banner-info" role="status">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <p>
                    <span class="cil-banner-title"><?php esc_html_e('One step to first suggestion: paste your OpenAI API key.', 'champlin-ai-internal-linker'); ?></span>
                    <?php
                    printf(
                        /* translators: %s: anchor tag linking to OpenAI's API key dashboard. */
                        esc_html__('Get a key at %s (5 minutes, free tier covers a year of typical use). Or skip the key with Premium hosted AI ($39/yr) — see below.', 'champlin-ai-internal-linker'),
                        '<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com/api-keys</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constant anchor with safe URL
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields('cil_settings_group'); ?>

            <!-- ============================================================
                 SECTION 1 — Connection
                 ============================================================ -->
            <section class="cil-card cil-card--striped">
                <header class="cil-card-header">
                    <div>
                        <h2><?php esc_html_e('Connection', 'champlin-ai-internal-linker'); ?></h2>
                        <p class="cil-help"><?php esc_html_e('Your OpenAI account powers the semantic embedding lookups. Your key is stored only in this WordPress install and sent only to api.openai.com.', 'champlin-ai-internal-linker'); ?></p>
                    </div>
                    <span class="cil-pill cil-pill-idle"><span class="cil-pill-dot"></span> openai</span>
                </header>

                <div class="cil-card-body">
                    <?php if ($using_hosted_ai) : ?>
                        <div class="cil-banner cil-banner-premium" style="margin-bottom: 1.25rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.39 7.36H22l-6.19 4.5L18.2 21 12 16.5 5.8 21l2.39-7.14L2 9.36h7.61z"/></svg>
                            <p>
                                <span class="cil-banner-title"><?php echo esc_html($provider_summary['label']); ?></span>
                                <?php echo esc_html($provider_summary['hint']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="cil-field">
                        <label class="cil-field-label" for="cil-api-key">
                            <?php esc_html_e('OpenAI API key', 'champlin-ai-internal-linker'); ?>
                            <span class="cil-field-required" title="<?php esc_attr_e('Required', 'champlin-ai-internal-linker'); ?>">*</span>
                            <span class="cil-tooltip" tabindex="0">?
                                <span class="cil-tooltip-body"><?php esc_html_e('Begins with "sk-" (or "sk-proj-"). Used only for embedding calls — never for chat, never logged.', 'champlin-ai-internal-linker'); ?></span>
                            </span>
                        </label>
                        <div class="cil-input-group">
                            <input
                                type="password"
                                id="cil-api-key"
                                name="cil_settings[api_key]"
                                value="<?php echo esc_attr($settings['api_key']); ?>"
                                class="cil-input cil-input--mono"
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="sk-proj-…"
                            />
                            <?php if ($has_key) : ?>
                                <span class="cil-input-suffix cil-input-suffix-valid">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    <?php esc_html_e('saved', 'champlin-ai-internal-linker'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="cil-field-hint">
                            <?php esc_html_e('Typical site (~500 posts, ~50 edits/month): expect ≈ $0.04/year in OpenAI usage.', 'champlin-ai-internal-linker'); ?>
                            <a href="https://platform.openai.com/usage" target="_blank" rel="noopener"><?php esc_html_e('View your usage ↗', 'champlin-ai-internal-linker'); ?></a>
                        </p>

                        <!-- Premium upsell — subtle but present -->
                        <div class="cil-premium-bar">
                            <div class="cil-premium-bar-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.39 7.36H22l-6.19 4.5L18.2 21 12 16.5 5.8 21l2.39-7.14L2 9.36h7.61z"/></svg>
                            </div>
                            <div class="cil-premium-bar-body">
                                <h4><?php esc_html_e('Don’t want to manage an API key?', 'champlin-ai-internal-linker'); ?></h4>
                                <p><?php esc_html_e('Premium ($39/yr) includes hosted AI — no key required, no per-call billing. Same embeddings, our pipe.', 'champlin-ai-internal-linker'); ?></p>
                            </div>
                            <div class="cil-premium-bar-actions">
                                <span class="cil-pill cil-pill-premium">Coming v1.2</span>
                                <a href="https://kevinchamplin.com/plugins/champlin-ai-internal-linker#tiers" target="_blank" rel="noopener" class="cil-btn cil-btn-ghost cil-btn-sm">
                                    <?php esc_html_e('See Premium', 'champlin-ai-internal-linker'); ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="cil-field">
                        <label class="cil-field-label" for="cil-model">
                            <?php esc_html_e('Embedding model', 'champlin-ai-internal-linker'); ?>
                            <span class="cil-tooltip" tabindex="0">?
                                <span class="cil-tooltip-body"><?php esc_html_e('Larger models are higher quality but ~6× the cost. The small model is excellent for most content sites.', 'champlin-ai-internal-linker'); ?></span>
                            </span>
                        </label>
                        <select id="cil-model" name="cil_settings[model]" class="cil-select cil-input--mono" style="max-width:24rem;">
                            <option value="text-embedding-3-small" <?php selected($settings['model'], 'text-embedding-3-small'); ?>>
                                text-embedding-3-small — 1536d · $0.020 /1M tok · recommended
                            </option>
                            <option value="text-embedding-3-large" <?php selected($settings['model'], 'text-embedding-3-large'); ?>>
                                text-embedding-3-large — 3072d · $0.130 /1M tok · higher quality
                            </option>
                        </select>
                        <p class="cil-field-hint"><?php esc_html_e('Changing the model invalidates existing embeddings — you’ll want to Re-index after.', 'champlin-ai-internal-linker'); ?></p>
                    </div>

                    <input type="hidden" name="cil_settings[provider]" value="openai" />
                </div>
            </section>

            <!-- ============================================================
                 SECTION 2 — Suggestion behavior
                 ============================================================ -->
            <section class="cil-card">
                <header class="cil-card-header">
                    <div>
                        <h2><?php esc_html_e('Suggestion behavior', 'champlin-ai-internal-linker'); ?></h2>
                        <p class="cil-help"><?php esc_html_e('Control how aggressively the plugin surfaces internal-link suggestions in the editor sidebar.', 'champlin-ai-internal-linker'); ?></p>
                    </div>
                </header>

                <div class="cil-card-body">
                    <div class="cil-field">
                        <label class="cil-field-label" for="cil-threshold">
                            <?php esc_html_e('Similarity threshold', 'champlin-ai-internal-linker'); ?>
                            <span class="cil-tooltip" tabindex="0">?
                                <span class="cil-tooltip-body"><?php esc_html_e('Cosine similarity between embeddings. Higher = stricter match = fewer, more relevant suggestions. Lower = looser = more candidates but more noise.', 'champlin-ai-internal-linker'); ?></span>
                            </span>
                        </label>
                        <div class="cil-slider-wrap">
                            <input
                                type="range"
                                id="cil-threshold"
                                name="cil_settings[threshold]"
                                min="0" max="1" step="0.01"
                                value="<?php echo esc_attr($threshold_label); ?>"
                                class="cil-slider"
                                oninput="document.getElementById('cil-threshold-value').textContent = parseFloat(this.value).toFixed(2)"
                            />
                            <span id="cil-threshold-value" class="cil-slider-value tabular-nums"><?php echo esc_html(number_format((float) $threshold_label, 2)); ?></span>
                        </div>
                        <div class="cil-slider-scale">
                            <span>0 — loose recall</span>
                            <span>0.55 default</span>
                            <span>1 — strict precision</span>
                        </div>
                        <p class="cil-field-hint"><?php esc_html_e('Default 0.55 is calibrated to OpenAI text-embedding-3-small on real WordPress content. Identical posts score ~1.0; genuinely related posts cluster 0.55–0.75; unrelated drop below 0.50. Push above 0.65 if you only want very tight matches; drop below 0.50 if you want more candidates to skim.', 'champlin-ai-internal-linker'); ?></p>
                    </div>

                    <div class="cil-field">
                        <label class="cil-field-label" for="cil-max">
                            <?php esc_html_e('Max suggestions per post', 'champlin-ai-internal-linker'); ?>
                            <span class="cil-tooltip" tabindex="0">?
                                <span class="cil-tooltip-body"><?php esc_html_e('Hard cap on the sidebar list. Even if more candidates match, only this many show.', 'champlin-ai-internal-linker'); ?></span>
                            </span>
                        </label>
                        <input
                            type="number"
                            id="cil-max"
                            name="cil_settings[max_suggestions]"
                            min="1" max="50"
                            value="<?php echo esc_attr((string) $settings['max_suggestions']); ?>"
                            class="cil-input cil-input--narrow tabular-nums"
                        />
                        <p class="cil-field-hint"><?php esc_html_e('5 is the sweet spot for editorial flow. Increase for power users; decrease to keep the sidebar tidy.', 'champlin-ai-internal-linker'); ?></p>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 SECTION 3 — Content scope
                 ============================================================ -->
            <section class="cil-card">
                <header class="cil-card-header">
                    <div>
                        <h2><?php esc_html_e('Content scope', 'champlin-ai-internal-linker'); ?></h2>
                        <p class="cil-help"><?php esc_html_e('Which post types should be indexed and suggested. Only published content gets embedded.', 'champlin-ai-internal-linker'); ?></p>
                    </div>
                </header>
                <div class="cil-card-body">
                    <div class="cil-field">
                        <label class="cil-field-label">
                            <?php esc_html_e('Post types to index', 'champlin-ai-internal-linker'); ?>
                        </label>
                        <div class="cil-checklist">
                            <?php foreach ($post_types as $pt) : ?>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="cil_settings[post_types][]"
                                        value="<?php echo esc_attr($pt->name); ?>"
                                        <?php checked(in_array($pt->name, $settings['post_types'], true)); ?>
                                    />
                                    <span class="cil-checklist-text">
                                        <?php echo esc_html($pt->label); ?>
                                        <code><?php echo esc_html($pt->name); ?></code>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="cil-field-hint"><?php esc_html_e('Tip: deselect a type to remove it from suggestions without losing existing embeddings (they’ll be re-included if you toggle back on).', 'champlin-ai-internal-linker'); ?></p>
                    </div>
                </div>
            </section>

            <?php
            /**
             * Extension point: Pro and other add-ons render extra settings
             * sections here. Implementations should output a `<section class="cil-card">`.
             * Documented in docs/HOOKS.md as part of the public Pro API contract.
             *
             * @param array $settings Current cil_settings (sanitized).
             */
            do_action('cil_settings_render_extra', $settings);
            ?>

            <!-- ============================================================
                 SECTION 4 — Exclusions
                 ============================================================ -->
            <section class="cil-card">
                <header class="cil-card-header">
                    <div>
                        <h2><?php esc_html_e('Exclusions', 'champlin-ai-internal-linker'); ?></h2>
                        <p class="cil-help"><?php esc_html_e('Pages and categories that should never appear as suggestions — even when they’re semantically relevant.', 'champlin-ai-internal-linker'); ?></p>
                    </div>
                </header>
                <div class="cil-card-body">
                    <div class="cil-field">
                        <label class="cil-field-label" for="cil-ignored-posts">
                            <?php esc_html_e('Ignored post IDs', 'champlin-ai-internal-linker'); ?>
                            <span class="cil-tooltip" tabindex="0">?
                                <span class="cil-tooltip-body"><?php esc_html_e('Find a post ID by hovering its row in wp-admin → Posts. The number after "post=" in the edit URL.', 'champlin-ai-internal-linker'); ?></span>
                            </span>
                        </label>
                        <input
                            type="text"
                            id="cil-ignored-posts"
                            name="cil_settings[ignored_post_ids]"
                            value="<?php echo esc_attr(implode(', ', array_map('intval', $settings['ignored_post_ids']))); ?>"
                            class="cil-input cil-input--mono"
                            placeholder="42, 188, 902"
                        />
                        <p class="cil-field-hint"><?php esc_html_e('Comma-separated. Good fits: landing pages, legal/privacy pages, login walls, anything that shouldn’t be auto-suggested.', 'champlin-ai-internal-linker'); ?></p>
                    </div>

                    <div class="cil-field">
                        <label class="cil-field-label" for="cil-ignored-terms">
                            <?php esc_html_e('Ignored category IDs', 'champlin-ai-internal-linker'); ?>
                            <span class="cil-tooltip" tabindex="0">?
                                <span class="cil-tooltip-body"><?php esc_html_e('Excludes every published post in the listed category — and its child categories — recursively.', 'champlin-ai-internal-linker'); ?></span>
                            </span>
                        </label>
                        <input
                            type="text"
                            id="cil-ignored-terms"
                            name="cil_settings[ignored_term_ids]"
                            value="<?php echo esc_attr(implode(', ', array_map('intval', $settings['ignored_term_ids']))); ?>"
                            class="cil-input cil-input--mono"
                            placeholder="7, 19"
                        />
                        <p class="cil-field-hint"><?php esc_html_e('Comma-separated category term IDs. Useful for member-only content trees or archived sections.', 'champlin-ai-internal-linker'); ?></p>
                    </div>
                </div>

                <div class="cil-card-footer">
                    <div class="cil-savebar-status">
                        <?php
                        printf(
                            /* translators: placeholder for "Indexed N posts" or similar */
                            esc_html__('Changes take effect on save. Suggestion sidebar refreshes on the next post load.', 'champlin-ai-internal-linker')
                        );
                        ?>
                    </div>
                    <button type="submit" class="cil-btn cil-btn-primary cil-btn-lg">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <?php esc_html_e('Save settings', 'champlin-ai-internal-linker'); ?>
                    </button>
                </div>
            </section>
        </form>

        <footer class="cil-app-footer">
            <span>
                <?php esc_html_e('Engineered by', 'champlin-ai-internal-linker'); ?>
                <a href="https://champlinenterprises.com" target="_blank" rel="noopener">Champlin Enterprises</a>
            </span>
            <?php if ($cil_version) : ?>
                <span class="cil-version-chip">v<?php echo esc_html($cil_version); ?></span>
            <?php endif; ?>
        </footer>
    </div>
</div>
