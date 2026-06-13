<?php
/**
 * Extract a post's *renderable* content, including content stored in
 * shortcode-based page builders (Divi, WPBakery / Visual Composer).
 *
 * The plugin's normalizer takes the output of this class and produces the text
 * that gets embedded — so anything not surfaced here is invisible to the
 * suggestion engine.
 *
 * Page builders covered:
 *   - Divi (its layout shortcodes [et_pb_*] expand to readable HTML)
 *   - WPBakery / Visual Composer (its [vc_*] shortcodes)
 *   - Elementor (content lives in `_elementor_data` JSON, not post_content;
 *     extracted via ElementorExtractor — text + internal-link anchors)
 *   - Any custom shortcodes registered on the site
 *
 * Not yet covered (deferred to a future release; tracked in CHANGELOG):
 *   - ACF text fields (no canonical "what text is searchable?" answer; needs
 *     per-field-group configuration)
 *
 * @package Champlin\InternalLinker\Indexing
 */

declare(strict_types=1);

namespace Champlin\InternalLinker\Indexing;

use WP_Post;

final class ContentExtractor
{
    /**
     * Return the post's renderable content, with shortcodes expanded.
     *
     * If `do_shortcode()` throws or the resulting string is empty/much shorter
     * than the raw content, we fall back to the raw content rather than risk
     * losing text. (Some shortcodes return empty strings outside their normal
     * runtime context; we'd rather embed a noisy raw shortcode than nothing.)
     */
    public function extract(WP_Post $post): string
    {
        $raw      = (string) $post->post_content;
        $expanded = $raw === '' ? '' : $this->expand_shortcodes($raw, $post);

        // Elementor keeps content in `_elementor_data`, not post_content — so a
        // builder page often has empty/near-empty post_content. Append its
        // parsed text + internal links so the page is visible to embeddings and
        // the link graph.
        $elementor = new ElementorExtractor();
        if ($elementor->is_elementor((int) $post->ID)) {
            $el = $elementor->extract((int) $post->ID);
            if ($el !== '') {
                $expanded = $expanded === '' ? $el : $expanded . "\n" . $el;
            }
        }

        return $expanded;
    }

    /**
     * Expand shortcode-based page builders (Divi, WPBakery, custom shortcodes)
     * into renderable HTML. Falls back to the raw content if expansion throws or
     * collapses the text (some shortcodes return empty outside their runtime).
     */
    private function expand_shortcodes(string $raw, WP_Post $post): string
    {
        if (!function_exists('do_shortcode')) {
            return $raw;
        }

        // Some shortcodes inspect the $post global — set it so they resolve
        // correctly, then restore on the way out. We use $GLOBALS rather than
        // `global $post;` because the method parameter is also named $post and
        // would shadow.
        $had_global       = array_key_exists('post', $GLOBALS);
        $original_post    = $had_global ? $GLOBALS['post'] : null;
        $GLOBALS['post']  = $post;
        if (function_exists('setup_postdata')) {
            setup_postdata($post);
        }

        try {
            $expanded = do_shortcode($raw);
        } catch (\Throwable $e) {
            $expanded = $raw;
        } finally {
            if ($had_global) {
                $GLOBALS['post'] = $original_post;
            } else {
                unset($GLOBALS['post']);
            }
            if (function_exists('wp_reset_postdata')) {
                wp_reset_postdata();
            }
        }

        if ($expanded === '' || mb_strlen($expanded) < (int) (mb_strlen($raw) * 0.3)) {
            return $raw;
        }

        return $expanded;
    }
}
