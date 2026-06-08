# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ltools** (Locale Tools) is a Drupal module that wraps the `locale` module's APIs to simplify programmatic import and management of translations. It is a dev-tool/utility library, not a user-facing feature module.

- Drupal core compatibility: `^10.3 || ^11`
- Depends on: `locale` (Drupal core)
- Package: `drupal/ltools`

## Module Structure

```
ltools.info.yml          — module metadata
ltools.module            — thin backward-compatible wrappers
ltools.services.yml      — service definitions
src/Service/
  TranslationManager.php   — PO import (Gettext::fileToDatabase) + single-string import (locale.storage)
  PoFileFinder.php         — recursive/non-recursive .po discovery
  LocaleCacheManager.php   — locale cache invalidation via CacheTagsInvalidatorInterface
  LanguageProvisioner.php  — on-demand language creation
composer.json            — Composer package definition (PSR-4 autoloading)
tests/src/Kernel/
  LtoolsTranslationTest.php — KernelTestBase integration tests
.github/workflows/ci.yml — CI: composer validate, PHP lint, PHPCS, PHPUnit
```

## Public API

### Via services (primary usage)

```php
// Import a .po file, optionally creating the language first
\Drupal::service('ltools.translation_manager')
  ->importPoFile('fr', '/path/to/fr.po', TRUE);

// Find .po files recursively
\Drupal::service('ltools.po_file_finder')
  ->findPoFiles('/path/to/translations', TRUE);

// Add a single translation string
\Drupal::service('ltools.translation_manager')
  ->addTranslation('Hello', 'Bonjour', 'fr');

// Clear locale cache
\Drupal::service('ltools.locale_cache_manager')->clearLocaleCaches();
```

### Via legacy wrappers in `ltools.module` (backward compatibility)

`ltools_update_load_language()`, `ltools_find_po_files()`, `ltools_add_translation()`, `ltools_clear_cache()` — these delegate to the services above.

## Architecture Notes

`TranslationManager` uses only D10/11 public APIs:

- **`importPoFile()`** — delegates to `Drupal\locale\Gettext::fileToDatabase($file, $options)` where `$file` is a `\stdClass` with `uri`, `langcode`, and `filename` properties. The `overwrite_options` array controls whether customized and non-customized strings are replaced.
- **`addTranslation()`** — uses `locale.storage` (`StringStorageInterface`) to find-or-create source strings and translations. Strings added via this method are marked `LOCALE_CUSTOMIZED`.
- **`LocaleCacheManager`** — calls `CacheTagsInvalidatorInterface::invalidateTags(['locale'])`.

There is no `LocaleApiAdapter`. No D7 private functions (`_locale_import_po`, etc.) are used anywhere.

## Development

Install into a Drupal 10.3+ or 11 site:

```bash
composer require drupal/ltools
drush en ltools -y
```

Run CI checks locally:

```bash
composer install
phpcs --standard=Drupal,DrupalPractice src/ ltools.module
```

## Coding Conventions

- Drupal coding standards with 2-space indentation.
- All procedural functions prefixed `ltools_`.
- New logic goes into `src/Service/` classes with dependency injection via `ltools.services.yml`.
