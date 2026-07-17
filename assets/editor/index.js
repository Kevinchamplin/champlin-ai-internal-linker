/**
 * Champlin AI Internal Linker — Gutenberg sidebar entry.
 *
 * Renders a sidebar in the block editor that lists semantically relevant
 * internal-link suggestions for the post being edited. Click "Insert" to
 * inject an <a> into the active block at the suggested anchor offset.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, Button, Spinner, RangeControl, Notice } from '@wordpress/components';
import { useState, useEffect, useCallback, createElement, Fragment } from '@wordpress/element';
import { useSelect, useDispatch, dispatch as wpDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

const PLUGIN_NAME = 'champlin-internal-linker';
const SIDEBAR_NAME = 'chail-sidebar';

/**
 * If the editor URL carries `?chail_open=1`, auto-open the sidebar once the
 * editor is ready. This is what fixes the orphan-report workflow — the
 * candidate "Open in editor" buttons append that param so users land here
 * with the sidebar already open instead of hunting in the kebab menu.
 */
function maybeAutoOpenSidebar() {
    try {
        const params = new URLSearchParams(window.location.search);
        if (params.get('chail_open') !== '1') return;
        if (window.__chailSidebarAutoOpened) return;
        const tryOpen = () => {
            const editPost = wpDispatch('core/edit-post');
            if (editPost && typeof editPost.openGeneralSidebar === 'function') {
                editPost.openGeneralSidebar(`${PLUGIN_NAME}/${SIDEBAR_NAME}`);
                window.__chailSidebarAutoOpened = true;
                return true;
            }
            return false;
        };
        if (!tryOpen()) {
            // wp.data may not be wired up at script-load time on some themes.
            const interval = setInterval(() => {
                if (tryOpen()) clearInterval(interval);
            }, 200);
            setTimeout(() => clearInterval(interval), 5000);
        }
    } catch (_) { /* silent */ }
}
maybeAutoOpenSidebar();

/**
 * Editor config injected by EditorAssets::enqueue() via wp_localize_script.
 * We defensively coerce every numeric to a real Number — wp_localize_script
 * can occasionally hand back stringified floats depending on WP version and
 * sandbox, and a string value passed to RangeControl renders blank.
 */
const rawCfg = window.chailEditor || {};
const DEFAULT_THRESHOLD = 0.55;   // calibrated for OpenAI text-embedding-3-small
const DEFAULT_MAX_SUGGESTIONS = 5;

function coerceFloat(value, fallback) {
    const n = parseFloat(value);
    return Number.isFinite(n) ? n : fallback;
}
function coerceInt(value, fallback) {
    const n = parseInt(value, 10);
    return Number.isFinite(n) && n > 0 ? n : fallback;
}

const cfg = {
    nonce:          typeof rawCfg.nonce === 'string'         ? rawCfg.nonce         : '',
    restNamespace:  typeof rawCfg.restNamespace === 'string' ? rawCfg.restNamespace : 'chail/v1',
    threshold:      coerceFloat(rawCfg.threshold,      DEFAULT_THRESHOLD),
    maxSuggestions: coerceInt(rawCfg.maxSuggestions,   DEFAULT_MAX_SUGGESTIONS),
};

apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));

function InternalLinkerSidebar() {
    const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);
    const isSavedPost = useSelect((select) => {
        const editor = select('core/editor');
        return postId && !editor.isEditedPostNew();
    }, [postId]);
    const { insertBlock } = useDispatch('core/block-editor');

    const [suggestions, setSuggestions] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [threshold, setThresholdRaw] = useState(cfg.threshold);

    // Always store the threshold as a real Number, defensively.
    const setThreshold = useCallback((v) => {
        setThresholdRaw(coerceFloat(v, cfg.threshold));
    }, []);

    const fetchSuggestions = useCallback(() => {
        if (!postId) return;
        setLoading(true);
        setError(null);
        const t = coerceFloat(threshold, cfg.threshold).toFixed(2);
        apiFetch({
            path: `/${cfg.restNamespace}/suggestions/${postId}?limit=${cfg.maxSuggestions}&threshold=${t}`,
        })
            .then((rows) => {
                setSuggestions(Array.isArray(rows) ? rows : []);
                setLoading(false);
            })
            .catch((err) => {
                setError(err && err.message ? err.message : __('Failed to load suggestions.', PLUGIN_NAME));
                setLoading(false);
            });
    }, [postId, threshold]);

    useEffect(() => {
        if (isSavedPost) {
            fetchSuggestions();
        }
    }, [isSavedPost, fetchSuggestions]);

    const insertLink = useCallback((s) => {
        const anchor = String(s.suggested_anchor || s.title || '').trim();
        const url = String(s.permalink || '');
        if (anchor === '' || url === '') return;

        const result = wrapAnchorInExistingBlocks(anchor, url, s.title);
        const noticesDispatch = wp.data.dispatch('core/notices');

        if (result.inserted) {
            // Highlight the modified block so the user sees the change.
            wp.data.dispatch('core/block-editor').selectBlock(result.blockId);
            if (noticesDispatch && noticesDispatch.createSuccessNotice) {
                noticesDispatch.createSuccessNotice(
                    sprintf(__('Linked "%1$s" → %2$s', PLUGIN_NAME), result.matchedText, s.title),
                    { type: 'snackbar', isDismissible: true }
                );
            }
        } else {
            // Fallback: append at the end so the user still gets a working link
            // they can move/rephrase. Notice tells them why.
            const safeUrl = url.replace(/"/g, '&quot;');
            const safeTitle = String(s.title || '').replace(/"/g, '&quot;');
            const html = `<!-- wp:paragraph --><p>Related: <a href="${safeUrl}" title="${safeTitle}">${anchor}</a></p><!-- /wp:paragraph -->`;
            const block = wp.blocks.rawHandler({ HTML: html });
            if (block && block.length > 0) {
                insertBlock(block[0]);
            }
            if (noticesDispatch && noticesDispatch.createInfoNotice) {
                noticesDispatch.createInfoNotice(
                    sprintf(__('Anchor "%s" wasn\'t in your draft — added as a "Related" link at the end. Move or edit freely.', PLUGIN_NAME), anchor),
                    { type: 'snackbar', isDismissible: true }
                );
            }
        }

        apiFetch({
            path: `/${cfg.restNamespace}/suggestions/${postId}/accept`,
            method: 'POST',
            data: { target_post_id: s.post_id, post_id: postId },
        });
        setSuggestions((prev) => prev.filter((row) => row.post_id !== s.post_id));
    }, [insertBlock, postId]);

    /**
     * Walk the block tree (paragraphs, headings, lists, quotes, columns/groups
     * inner blocks) looking for a block whose content contains the anchor as
     * plain text (case-insensitive). Wrap the first viable match in an
     * <a href> and update the block in place.
     *
     * Skips:
     *   - Anchor already inside an <a>…</a>
     *   - Anchor inside an HTML attribute
     *
     * Returns { inserted: bool, blockId?, matchedText? }.
     */
    function wrapAnchorInExistingBlocks(anchor, url, targetTitle) {
        const blocks = wp.data.select('core/block-editor').getBlocks();
        const result = walk(blocks);
        return result;

        function walk(blockList) {
            for (const block of blockList) {
                const editableAttrs = ['content', 'value', 'caption', 'text', 'citation'];
                for (const attr of editableAttrs) {
                    const content = block.attributes?.[attr];
                    if (typeof content !== 'string' || content === '') continue;
                    const wrapped = tryWrapInContent(content, anchor, url, targetTitle);
                    if (wrapped) {
                        const patch = {};
                        patch[attr] = wrapped.newContent;
                        wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, patch);
                        return { inserted: true, blockId: block.clientId, matchedText: wrapped.matchedText };
                    }
                }
                if (block.innerBlocks && block.innerBlocks.length > 0) {
                    const inner = walk(block.innerBlocks);
                    if (inner.inserted) return inner;
                }
            }
            return { inserted: false };
        }
    }

    function tryWrapInContent(content, anchor, url, targetTitle) {
        const lowerContent = content.toLowerCase();
        const lowerAnchor = anchor.toLowerCase();
        let pos = lowerContent.indexOf(lowerAnchor);
        while (pos >= 0) {
            if (isInsideExistingAnchor(content, pos) || isInsideTag(content, pos)) {
                pos = lowerContent.indexOf(lowerAnchor, pos + 1);
                continue;
            }
            const before = content.substring(0, pos);
            const matched = content.substring(pos, pos + anchor.length);
            const after = content.substring(pos + anchor.length);
            const safeUrl = url.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
            const safeTitle = String(targetTitle || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');
            const newContent = `${before}<a href="${safeUrl}" title="${safeTitle}">${matched}</a>${after}`;
            return { newContent, matchedText: matched };
        }
        return null;
    }

    function isInsideExistingAnchor(content, position) {
        const before = content.substring(0, position).toLowerCase();
        const lastOpen = before.lastIndexOf('<a ');
        const lastClose = before.lastIndexOf('</a>');
        return lastOpen > lastClose;
    }

    function isInsideTag(content, position) {
        const before = content.substring(0, position);
        const lastLt = before.lastIndexOf('<');
        const lastGt = before.lastIndexOf('>');
        return lastLt > lastGt;
    }

    return createElement(Fragment, null,
        createElement(PluginSidebarMoreMenuItem, { target: 'chail-sidebar', icon: 'admin-links' },
            __('AI Internal Linker', PLUGIN_NAME)
        ),
        createElement(PluginSidebar, {
            name: 'chail-sidebar',
            title: __('AI Internal Linker', PLUGIN_NAME),
            icon: 'admin-links',
        },
            createElement(PanelBody, { title: __('Settings', PLUGIN_NAME), initialOpen: false },
                createElement(RangeControl, {
                    label: __('Similarity threshold', PLUGIN_NAME),
                    value: coerceFloat(threshold, cfg.threshold),
                    onChange: setThreshold,
                    min: 0,
                    max: 1,
                    step: 0.05,
                    help: sprintf(
                        __('Saved default: %s (Settings → AI Linker). Adjustments here apply for this editor session only.', PLUGIN_NAME),
                        cfg.threshold.toFixed(2)
                    ),
                }),
                createElement(Button, {
                    variant: 'link',
                    onClick: () => { setThreshold(cfg.threshold); setTimeout(fetchSuggestions, 50); },
                    style: { padding: 0, marginTop: '0.4rem', fontSize: '0.78rem' },
                    disabled: Math.abs(threshold - cfg.threshold) < 0.001,
                }, __('Reset to saved default', PLUGIN_NAME))
            ),
            createElement(PanelBody, { title: __('Suggestions', PLUGIN_NAME), initialOpen: true },
                !isSavedPost && createElement('p', null,
                    __('Save the post first to see suggestions.', PLUGIN_NAME)
                ),
                error && createElement(Notice, { status: 'error', isDismissible: false }, error),
                loading && createElement(Spinner, null),
                !loading && isSavedPost && suggestions.length === 0 && !error && createElement('div', {
                    style: { padding: '0.5rem 0' },
                },
                    createElement('p', { style: { margin: '0 0 0.6rem', fontSize: '0.85rem' } },
                        __('No semantic matches above threshold ', PLUGIN_NAME),
                        createElement('strong', null, threshold.toFixed(2)),
                        '.'
                    ),
                    createElement('p', { style: { margin: '0 0 0.8rem', fontSize: '0.8rem', color: '#6b7280' } },
                        __('Try lowering the threshold above — 0.55 is the typical sweet spot. If you still see nothing, this post may be a standalone topic on your site (which is fine).', PLUGIN_NAME)
                    ),
                    createElement(Button, {
                        variant: 'secondary',
                        onClick: () => { setThreshold(0.5); setTimeout(fetchSuggestions, 50); },
                        style: { width: '100%' },
                    }, __('Try threshold 0.50', PLUGIN_NAME))
                ),
                !loading && suggestions.map((s) => createElement('div', {
                    key: s.post_id,
                    className: 'chail-suggestion',
                    style: { padding: '0.75em 0', borderBottom: '1px solid #e5e7eb' },
                },
                    createElement('strong', null, s.title),
                    createElement('div', { style: { fontSize: '0.85em', color: '#6b7280', margin: '0.25em 0' } },
                        sprintf(__('Similarity: %s', PLUGIN_NAME), s.similarity.toFixed(3))
                    ),
                    s.suggested_anchor && createElement('div', { style: { fontSize: '0.9em', fontStyle: 'italic', margin: '0.25em 0' } },
                        '"' + s.suggested_anchor + '"'
                    ),
                    createElement(Button, {
                        variant: 'primary',
                        onClick: () => insertLink(s),
                        style: { marginTop: '0.5em' },
                    }, __('Insert link', PLUGIN_NAME))
                )),
                createElement(Button, {
                    variant: 'secondary',
                    onClick: fetchSuggestions,
                    style: { marginTop: '1em' },
                    disabled: loading || !isSavedPost,
                }, __('Refresh', PLUGIN_NAME))
            )
        )
    );
}

registerPlugin(PLUGIN_NAME, { render: InternalLinkerSidebar });
