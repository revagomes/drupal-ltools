# Locale Tools (`ltools`)

Drupal utility module for locale import workflows.

## Drupal compatibility

- Drupal 10.3+
- Drupal 11

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
