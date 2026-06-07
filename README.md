# Locale Tools (`ltools`)

Drupal utility module for locale import workflows.

## Drupal compatibility

- ✅ Drupal 10.3+
- ✅ Drupal 11

## What changed in this modernization

- Migrated legacy procedural business logic to services under `/src`.
- Kept `ltools.module` as thin backward-compatible wrappers.
- Isolated unavoidable locale internal API calls in `LocaleApiAdapter`.
- Updated module metadata to modern Drupal `.info.yml` format.

## Services

- `ltools.translation_manager`
  - Import PO files.
  - Add single translations.
- `ltools.po_file_finder`
  - Find `.po` files recursively/non-recursively.
- `ltools.locale_cache_manager`
  - Clear locale-related caches.
- `ltools.language_provisioner`
  - Optionally create missing languages.

## Backward-compatible procedural wrappers

The legacy helpers remain available:

- `ltools_update_load_language()`
- `ltools_find_po_files()`
- `ltools_add_translation()`
- `ltools_clear_cache()`

They now delegate to services and are intended for migration compatibility.

## Notes on locale internals

Drupal core locale import still relies on underscore-prefixed APIs for some operations.
This module isolates those calls in `Drupal\ltools\Service\LocaleApiAdapter` to limit maintenance risk.

## Example usage

```php
$imported = \Drupal::service('ltools.translation_manager')->importPoFile('fr', '/var/translations/fr.po');
$files = \Drupal::service('ltools.po_file_finder')->findPoFiles('/var/translations', TRUE);
\Drupal::service('ltools.translation_manager')->addTranslation('Hello', 'Bonjour', 'fr');
```
