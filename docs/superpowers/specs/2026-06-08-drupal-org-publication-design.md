# ltools — drupal.org Publication Design

**Date:** 2026-06-08  
**Status:** Approved  
**Scope:** Fix broken D10/11 runtime APIs, add tests, and complete packaging for drupal.org publication

---

## Problem

`LocaleApiAdapter` calls D7 internal functions (`_locale_import_po`, `_locale_import_one_string_db`, `_locale_invalidate_js`) that do not exist in Drupal 10/11. Every call to `importPoFile()` or `addTranslation()` throws a `RuntimeException` at runtime. The module is non-functional as shipped.

Additional blockers for drupal.org: missing `LICENSE.txt`, wrong `composer.json` package name, no `.gitignore`, no tests.

---

## Approach

**Approach B — Remove `LocaleApiAdapter`, use D10/11 public APIs directly.**

The adapter pattern was justified when wrapping D7 private functions. On D10/11 public APIs (`locale.storage`, `Gettext::fileToDatabase()`), the abstraction adds no value. `LocaleApiAdapter` is deleted; its callers adopt the public APIs directly.

---

## Architecture

### Services

| Service ID | Class | Status |
|---|---|---|
| `ltools.translation_manager` | `TranslationManager` | Rewritten |
| `ltools.locale_cache_manager` | `LocaleCacheManager` | Simplified |
| `ltools.language_provisioner` | `LanguageProvisioner` | Unchanged |
| `ltools.po_file_finder` | `PoFileFinder` | Unchanged |
| `ltools.locale_api_adapter` | `LocaleApiAdapter` | **Deleted** |

### `TranslationManager`

New constructor dependencies:

```php
public function __construct(
  protected StringStorageInterface $localeStorage,      // locale.storage
  protected LanguageManagerInterface $languageManager,  // language_manager
  protected LanguageProvisioner $languageProvisioner,
  protected LocaleCacheManager $localeCacheManager,
  protected LoggerInterface $logger,
)
```

**`importPoFile()`** — delegates to `Gettext::fileToDatabase()`:

```php
$file = (object) ['uri' => $poFile, 'langcode' => $langcode];
$options = [
  'overwrite_options' => [
    'not_customized' => $overwrite,
    'customized' => $overwrite,
  ],
  'customized' => LOCALE_NOT_CUSTOMIZED,
];
return (bool) Gettext::fileToDatabase($file, $options);
```

**`addTranslation()`** — new signature accepts `$overwrite` flag (caller decides):

```php
public function addTranslation(
  string $source,
  string $translation,
  string $langcode,
  string $context = '',
  string $textgroup = 'default',
  bool $overwrite = TRUE,
): void
```

Implementation:
1. Find or create source string via `$localeStorage->findString(['source' => $source, 'context' => $context])`
2. Find existing translation via `$localeStorage->findTranslation(['lid' => $string->lid, 'language' => $langcode])`
3. If translation exists and `$overwrite = FALSE` and it is customized → skip
4. Otherwise create or update translation, mark as `LOCALE_CUSTOMIZED`
5. Call `$localeCacheManager->clearLocaleCaches()`

### `LocaleCacheManager`

Drops `LocaleApiAdapter` dependency entirely. `clearLocaleCaches()` becomes:

```php
public function clearLocaleCaches(): void {
  $this->cacheTagsInvalidator->invalidateTags(['locale']);
}
```

Constructor: `CacheTagsInvalidatorInterface` only.

### `ltools.services.yml`

- Remove `ltools.locale_api_adapter` service definition
- Update `ltools.translation_manager` arguments: `locale.storage`, `language_manager`, `ltools.language_provisioner`, `ltools.locale_cache_manager`, `logger.channel.ltools`
- Update `ltools.locale_cache_manager` arguments: `cache_tags.invalidator` only

### `ltools.module` backward-compatible wrappers

`ltools_add_translation()` wrapper gains optional `$overwrite = TRUE` parameter to expose the new flag while preserving existing call sites.

---

## Packaging & Repository Hygiene

### `composer.json`

- Name: `drupal/ltools`
- Add `php: ^8.1` to `require`
- Keep `drupal/core: ^10.3 || ^11` in `require`
- Keep `drupal/coder: ^8.3` in `require-dev`

### New files

- **`LICENSE.txt`** — GPL-2.0-or-later full text (required by drupal.org project review)
- **`.gitignore`** — `/vendor/`

### Updated files

- **`README.md`** — update `addTranslation()` signature, remove "What changed in this modernization" section (PR history, not docs)
- **`CLAUDE.md`** — reflect `LocaleApiAdapter` removal, updated constructor signatures, D10/11 development context

---

## Tests

**File:** `tests/src/Kernel/LtoolsTranslationTest.php`  
**Base class:** `KernelTestBase`  
**Module installs:** `locale`, `language`, `ltools`

| Method | Assertion |
|---|---|
| `testImportPoFile()` | Write temp `.po` file, import, verify translation in `locale.storage` |
| `testImportPoFileOverwrite()` | Import string, reimport with changed translation — overwrite=TRUE replaces, overwrite=FALSE keeps original |
| `testAddTranslation()` | Call `addTranslation()`, verify source + translation in `locale.storage` |
| `testAddTranslationRespectOverwrite()` | Add customized string, call again with overwrite=FALSE — original preserved |
| `testFindPoFiles()` | Temp dir tree with `.po` and non-`.po` files — only `.po` paths returned, sorted |

No FunctionalTest required — all assertions are at the service/DB level.

---

## Files Changed

| File | Change |
|---|---|
| `src/Service/LocaleApiAdapter.php` | Deleted |
| `src/Service/TranslationManager.php` | Rewritten |
| `src/Service/LocaleCacheManager.php` | Simplified |
| `ltools.services.yml` | Updated dependencies |
| `ltools.module` | `ltools_add_translation()` gains `$overwrite` param |
| `composer.json` | Name, php requirement |
| `LICENSE.txt` | New |
| `.gitignore` | New |
| `README.md` | Updated |
| `CLAUDE.md` | Updated |
| `tests/src/Kernel/LtoolsTranslationTest.php` | New |
