<?php
/**
 * Extract text + internal links from Elementor pages.
 *
 * Elementor stores a page's real content in the `_elementor_data` JSON meta,
 * NOT in `post_content` (which is usually empty on a builder page). Without this
 * class an Elementor page is invisible to BOTH:
 *   - the suggestion engine (no embeddable text), and
 *   - the link graph (no `<a href>` → every Elementor page looks orphaned, so
 *     the internal-link "structure" score collapses to 0).
 *
 * We PARSE the saved element tree rather than rendering the page. Rendering
 * needs Elementor's frontend bootstrapped, which is fragile inside background
 * Action Scheduler jobs; parsing is fast, dependency-free, and reliable. The
 * tradeoff is that dynamic / global / theme-builder widgets aren't expanded —
 * good enough for indexing + link discovery. (Full render is a future upgrade.)
 *
 * @package Champlin\InternalLinker\Indexing
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Indexing;

final class ElementorExtractor
{
    /**
     * Plain-text setting keys worth embedding. Rich-text fields (which carry
     * inline links + prose, e.g. the text-editor widget's `editor`) are caught
     * separately by sniffing for an `<a ` tag, so they work regardless of key.
     */
    private const TEXT_KEYS = [
        'title', 'text', 'editor', 'html', 'caption', 'description',
        'description_text', 'title_text', 'sub_title', 'sub_heading',
        'heading_title', 'testimonial_content', 'alert_title', 'alert_description',
        'tab_title', 'tab_content', 'accordion_title', 'item_title', 'item_description',
        'before_text', 'after_text', 'highlighted_text', 'rotating_text',
    ];

    /** Is this post built with Elementor (has saved builder data)? */
    public function is_elementor(int $post_id): bool
    {
        if ($post_id <= 0 || !function_exists('get_post_meta')) {
            return false;
        }
        if (get_post_meta($post_id, '_elementor_edit_mode', true) !== 'builder') {
            return false;
        }

        return (string) get_post_meta($post_id, '_elementor_data', true) !== '';
    }

    /**
     * Return an HTML blob with the page's text + internal-link anchors, parsed
     * from `_elementor_data`. Empty string if not Elementor or unparseable.
     */
    public function extract(int $post_id): string
    {
        $raw = function_exists('get_post_meta') ? get_post_meta($post_id, '_elementor_data', true) : '';
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return '';
        }

        $out = [];
        $this->walk($data, $out);

        return implode("\n", $out);
    }

    /**
     * @param array<mixed> $nodes
     * @param string[]     $out
     */
    private function walk(array $nodes, array &$out): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (isset($node['settings']) && is_array($node['settings'])) {
                $this->collect($node['settings'], $out);
            }
            if (!empty($node['elements']) && is_array($node['elements'])) {
                $this->walk($node['elements'], $out);
            }
        }
    }

    /**
     * Pull links + text out of one element's settings (recursing into grouped
     * controls / repeater rows).
     *
     * @param array<mixed> $settings
     * @param string[]     $out
     */
    private function collect(array $settings, array &$out): void
    {
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                // Elementor link/button controls nest the URL: ['url' => '...'].
                if (isset($value['url']) && is_string($value['url']) && $value['url'] !== '') {
                    $out[] = '<a href="' . esc_url($value['url']) . '"></a>';
                } else {
                    $this->collect($value, $out); // grouped controls / repeaters
                }
                continue;
            }

            if (!is_string($value) || $value === '') {
                continue;
            }

            // Rich text (text-editor `editor`, HTML widget) — keep verbatim so
            // inline <a href> links survive for the scanner AND the embedder.
            if (str_contains($value, '<a ')) {
                $out[] = $value;
                continue;
            }

            if (in_array((string) $key, self::TEXT_KEYS, true)) {
                $out[] = wp_strip_all_tags($value);
            }
        }
    }
}
