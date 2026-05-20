# Extension Hooks

The Free plugin exposes a small surface of WordPress filter + action hooks for
**Champlin AI Linker Pro** and community add-ons to extend behavior without
patching internals.

Treat this file as the **public API contract**. Changes to these hook names,
arguments, or call sites are breaking changes and bump the plugin's **MAJOR**
version under semantic versioning.

---

## Lifecycle hooks

### `cil_plugin_loaded`  (action)

Fires once, late in the bootstrap, after the Free plugin has registered all of
its core hooks but before WordPress's `init` action.

**Arguments**
- `Plugin $plugin` — the Free plugin's container instance.

**Use for:** Pro registering its own services, REST routes, admin menus.

```php
add_action('cil_plugin_loaded', function (\Champlin\InternalLinker\Plugin $free): void {
    \Champlin\InternalLinkerPro\Plugin::boot($free);
});
```

---

## Suggestion-engine hooks

### `cil_extra_excluded_ids`  (filter)

Fires inside `SuggestionEngine::suggestions_for()` after the base exclusion
set is computed (source post + acceptance log + ignored posts + ignored
categories) and **before** candidate iteration.

**Signature**
```php
apply_filters('cil_extra_excluded_ids', int[] $excluded, int $source_post_id, array $settings)
```

**Use for:** further narrowing which posts can be suggested. Example: orphan-
only mode, last-30-days-only mode, language-specific exclusions.

### `cil_rank_results`  (filter)

Fires after cosine ranking but **before** per-candidate post lookup + anchor
extraction.

**Signature**
```php
apply_filters('cil_rank_results', array $ranked, int $source_post_id, array $settings)
```

`$ranked` is a list of `['post_id' => int, 'similarity' => float]` entries,
sorted descending by similarity. Implementations may reorder, filter, or
inject — but must preserve the shape, and should re-sort if scores change.

**Use for:** Money Pages prioritization, focus-keyword boosts, custom
re-ranking strategies.

### `cil_suggestion_row`  (filter)

Fires once per suggestion immediately before it's added to the response
array.

**Signature**
```php
apply_filters('cil_suggestion_row', array $row_out, array $row, int $source_post_id)
```

`$row_out` shape must preserve these keys:
- `post_id` (int)
- `title` (string)
- `permalink` (string)
- `similarity` (float)
- `suggested_anchor` (string)
- `anchor_offset` (int)
- `target_keyword` (string)
- `target_keyword_source` (string)

**Use for:** attaching extra display data (badges, click predictions, broken-
link warnings) that the editor sidebar or REST consumer can render.

---

## Provider hooks

### `cil_provider`  (filter)

Fires inside `ProviderFactory::create()` before the default switch resolves
the provider.

**Signature**
```php
apply_filters('cil_provider', ?ProviderInterface $override, string $provider_slug, array $settings)
```

Return a fully-constructed `ProviderInterface` to override Free's default
resolver. Return `null` (or don't hook in) to fall through.

**Use for:** Pro's `HostedProvider` to swap in our hosted `/api/embed` proxy
when the user has a valid license. Critical for the "no OpenAI key needed"
upgrade path.

---

## Settings hooks

### `cil_settings_sanitized`  (filter)

Fires at the end of `SettingsPage::sanitize()`, after Free's own fields are
clean.

**Signature**
```php
apply_filters('cil_settings_sanitized', array $sanitized, array $input)
```

**Use for:** Pro sanitizing its added settings fields (license key, auto-link
rules JSON, Money Page IDs) so they get persisted in the same `cil_settings`
option, not in separate options.

---

## Stability

All hooks above are versioned at the plugin's MAJOR. Today that's `v1.x`.
A v2.0 release would be the only opportunity to remove or rename a hook.
Adding new hooks is a MINOR bump; renaming/removing is MAJOR.

The Pro plugin should declare its compatible Free range in its plugin header
(via the `Requires Plugins` directive added in WordPress 6.5) so users can't
activate a mismatched pair.

---

## Implementation reference (source of truth)

| Hook | File | Line context |
|---|---|---|
| `cil_plugin_loaded` | `src/Plugin.php` | end of `register()` |
| `cil_extra_excluded_ids` | `src/Engine/SuggestionEngine.php` | inside `suggestions_for()` |
| `cil_rank_results` | `src/Engine/SuggestionEngine.php` | after `cosine->rank()` |
| `cil_suggestion_row` | `src/Engine/SuggestionEngine.php` | inside the result loop |
| `cil_provider` | `src/Embeddings/ProviderFactory.php` | top of `create()` |
| `cil_settings_sanitized` | `src/Admin/SettingsPage.php` | end of `sanitize()` |
