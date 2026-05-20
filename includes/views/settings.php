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
// These variables are function-scope locals in SettingsPage::render(); Plugin Check
// can't see surrounding scope when it analyses an included view file.

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap cil-wrap">
    <h1><?php echo esc_html__('AI Internal Linker — Settings', 'champlin-internal-linker'); ?></h1>

    <?php settings_errors('cil_settings_group'); ?>

    <form method="post" action="options.php" class="cil-card">
        <?php settings_fields('cil_settings_group'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="cil-api-key"><?php esc_html_e('OpenAI API Key', 'champlin-internal-linker'); ?></label></th>
                <td>
                    <input
                        type="password"
                        id="cil-api-key"
                        name="cil_settings[api_key]"
                        value="<?php echo esc_attr($settings['api_key']); ?>"
                        class="regular-text"
                        autocomplete="off"
                    />
                    <p class="description"><?php esc_html_e('Stored in the WordPress options table. Never sent anywhere except api.openai.com.', 'champlin-internal-linker'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="cil-model"><?php esc_html_e('Embedding model', 'champlin-internal-linker'); ?></label></th>
                <td>
                    <select id="cil-model" name="cil_settings[model]">
                        <?php
                        $models = [
                            'text-embedding-3-small' => 'text-embedding-3-small (1536d, cheap)',
                            'text-embedding-3-large' => 'text-embedding-3-large (3072d, higher quality)',
                        ];
                        foreach ($models as $value => $label) :
                            ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['model'], $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="cil-threshold"><?php esc_html_e('Similarity threshold', 'champlin-internal-linker'); ?></label></th>
                <td>
                    <input
                        type="range"
                        id="cil-threshold"
                        name="cil_settings[threshold]"
                        min="0" max="1" step="0.01"
                        value="<?php echo esc_attr((string) $settings['threshold']); ?>"
                        oninput="document.getElementById('cil-threshold-value').textContent = this.value"
                    />
                    <span id="cil-threshold-value"><?php echo esc_html((string) $settings['threshold']); ?></span>
                    <p class="description"><?php esc_html_e('Suggestions below this cosine similarity are hidden. 0.75 is a sensible default.', 'champlin-internal-linker'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Post types to index', 'champlin-internal-linker'); ?></th>
                <td>
                    <?php foreach ($post_types as $pt) : ?>
                        <label style="display:block;margin:.25em 0">
                            <input
                                type="checkbox"
                                name="cil_settings[post_types][]"
                                value="<?php echo esc_attr($pt->name); ?>"
                                <?php checked(in_array($pt->name, $settings['post_types'], true)); ?>
                            />
                            <?php echo esc_html($pt->label); ?>
                            <code><?php echo esc_html($pt->name); ?></code>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="cil-max"><?php esc_html_e('Max suggestions per post', 'champlin-internal-linker'); ?></label></th>
                <td>
                    <input
                        type="number"
                        id="cil-max"
                        name="cil_settings[max_suggestions]"
                        min="1" max="50"
                        value="<?php echo esc_attr((string) $settings['max_suggestions']); ?>"
                        class="small-text"
                    />
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Provider', 'champlin-internal-linker'); ?></th>
                <td>
                    <input type="hidden" name="cil_settings[provider]" value="openai" />
                    <code>openai</code>
                    <p class="description"><?php esc_html_e('OpenAI is the only provider in v1. The plugin is built so additional providers can be added without changing call sites.', 'champlin-internal-linker'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save settings', 'champlin-internal-linker')); ?>
    </form>

    <p class="cil-footer-credit">
        <?php esc_html_e('Engineered by', 'champlin-internal-linker'); ?>
        <a href="https://champlinenterprises.com" target="_blank" rel="noreferrer noopener">Champlin Enterprises</a>
    </p>
</div>
