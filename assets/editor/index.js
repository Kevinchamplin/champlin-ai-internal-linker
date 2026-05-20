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
import { useSelect, useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

const PLUGIN_NAME = 'champlin-internal-linker';

const cfg = window.cilEditor || {
    nonce: '',
    restNamespace: 'cil/v1',
    threshold: 0.75,
    maxSuggestions: 5,
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
    const [threshold, setThreshold] = useState(cfg.threshold);

    const fetchSuggestions = useCallback(() => {
        if (!postId) return;
        setLoading(true);
        setError(null);
        apiFetch({
            path: `/${cfg.restNamespace}/suggestions/${postId}?limit=${cfg.maxSuggestions}&threshold=${threshold}`,
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
        const html = `<!-- wp:paragraph --><p><a href="${s.permalink}" title="${s.title.replace(/"/g, '&quot;')}">${s.suggested_anchor || s.title}</a></p><!-- /wp:paragraph -->`;
        const block = wp.blocks.rawHandler({ HTML: html });
        if (block && block.length > 0) {
            insertBlock(block[0]);
        }
        apiFetch({
            path: `/${cfg.restNamespace}/suggestions/${postId}/accept`,
            method: 'POST',
            data: { target_post_id: s.post_id, post_id: postId },
        });
        setSuggestions((prev) => prev.filter((row) => row.post_id !== s.post_id));
    }, [insertBlock, postId]);

    return createElement(Fragment, null,
        createElement(PluginSidebarMoreMenuItem, { target: 'cil-sidebar', icon: 'admin-links' },
            __('AI Internal Linker', PLUGIN_NAME)
        ),
        createElement(PluginSidebar, {
            name: 'cil-sidebar',
            title: __('AI Internal Linker', PLUGIN_NAME),
            icon: 'admin-links',
        },
            createElement(PanelBody, { title: __('Settings', PLUGIN_NAME), initialOpen: false },
                createElement(RangeControl, {
                    label: __('Similarity threshold', PLUGIN_NAME),
                    value: threshold,
                    onChange: (v) => setThreshold(v),
                    min: 0,
                    max: 1,
                    step: 0.05,
                })
            ),
            createElement(PanelBody, { title: __('Suggestions', PLUGIN_NAME), initialOpen: true },
                !isSavedPost && createElement('p', null,
                    __('Save the post first to see suggestions.', PLUGIN_NAME)
                ),
                error && createElement(Notice, { status: 'error', isDismissible: false }, error),
                loading && createElement(Spinner, null),
                !loading && isSavedPost && suggestions.length === 0 && !error && createElement('p', null,
                    __('No suggestions above the current threshold.', PLUGIN_NAME)
                ),
                !loading && suggestions.map((s) => createElement('div', {
                    key: s.post_id,
                    className: 'cil-suggestion',
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
