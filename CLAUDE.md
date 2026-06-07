# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ltools** (Locale Tools) is a Drupal 7.x helper module that wraps the core `locale` module's internal APIs to simplify programmatic import and management of translations. It is a dev-tool/utility library module, not a user-facing feature module.

- Drupal core compatibility: 7.x
- Depends on: `locale` (Drupal core)
- Package: Dev tool suite

## Module Structure

Two files only — no build system, no tests, no Composer:

- `ltools.info` — module metadata for Drupal 7
- `ltools.module` — all module logic

## Public API

### `ltools_update_load_language($langcode, $po_file, $new_lang, $mode, $group, $add_language_options)`

Imports a `.po` file into `locales_target`. Optionally registers a new language first via `locale_add_language()`. `$mode` is `LOCALE_IMPORT_OVERWRITE` or `LOCALE_IMPORT_KEEP`.

### `ltools_find_po_files($path, $recursive)`

Recursively (or not) finds all `.po` files under a directory. Returns an array of full paths.

### `ltools_add_translation($source, $translation, $langcode, $context, $textgroup)`

Adds a single translation string directly to the database via `_locale_import_one_string_db()`. Clears locale cache after insertion.

### `ltools_clear_cache()`

Invalidates the Drupal locale JS cache and clears the `locale:` cache bin.

## Development Context

This module must be developed inside a Drupal 7 site installation. Place it at `sites/all/modules/ltools/` (or a profile's modules directory), then enable it:

```bash
drush en ltools -y
```

To test translation import manually:

```php
// In a Drush php-eval or hook_update_N:
ltools_update_load_language('fr', '/path/to/fr.po');
```

## Coding Conventions

- Drupal 7 procedural PHP — no classes, no namespaces.
- All functions prefixed `ltools_`.
- Follow [Drupal coding standards](https://www.drupal.org/docs/develop/standards) (2-space indentation, `@param`/`@return` docblocks).
- Internal Drupal locale functions (prefixed `_locale_`) are considered stable enough to call here since this module targets Drupal 7 only.
