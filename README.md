# Locale Tools (`ltools`)

[![CI](https://github.com/revagomes/drupal-ltools/actions/workflows/ci.yml/badge.svg)](https://github.com/revagomes/drupal-ltools/actions/workflows/ci.yml)
[![Drupal 10.3+](https://img.shields.io/badge/Drupal-10.3%2B-blue)](https://www.drupal.org/project/ltools)
[![Drupal 11](https://img.shields.io/badge/Drupal-11-blue)](https://www.drupal.org/project/ltools)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

Drupal utility module for programmatic PO file import and translation management.

## Installation

```bash
composer require drupal/ltools
drush en ltools -y
```

## Services

- `ltools.translation_manager` — import PO files; add single translations
- `ltools.po_file_finder` — find `.po` files recursively or non-recursively
- `ltools.locale_cache_manager` — clear locale-related caches
- `ltools.language_provisioner` — create missing languages on demand

## Example usage

```php
// Import a PO file, optionally creating the language first.
\Drupal::service('ltools.translation_manager')
  ->importPoFile('fr', '/path/to/fr.po', TRUE);

// Find .po files recursively.
$files = \Drupal::service('ltools.po_file_finder')
  ->findPoFiles('/path/to/translations', TRUE);

// Add a single translation string.
\Drupal::service('ltools.translation_manager')
  ->addTranslation('Hello', 'Bonjour', 'fr');

// Clear locale cache.
\Drupal::service('ltools.locale_cache_manager')->clearLocaleCaches();
```

## Backward-compatible procedural wrappers

Legacy helpers remain available and delegate to the services above:

- `ltools_update_load_language($langcode, $po_file, $new_lang, $overwrite, $group, $options)`
- `ltools_find_po_files($path, $recursive)`
- `ltools_add_translation($source, $translation, $langcode, $context, $textgroup, $overwrite)`
- `ltools_clear_cache()`
