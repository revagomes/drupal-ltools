# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2026-06-09

### Added
- `KernelTestBase` integration tests covering PO import, overwrite behaviour, single-string add, and `.po` file discovery
- `LICENSE.txt` (GPL-2.0-or-later) required for drupal.org publication
- `.gitignore` excluding `/vendor/`
- `phpcs.xml.dist` scoping PHPCS to module source files

### Changed
- `TranslationManager::importPoFile()` now uses `Drupal\locale\Gettext::fileToDatabase()` — no D7 private functions
- `TranslationManager::addTranslation()` now uses `locale.storage` (`StringStorageInterface`) directly
- `LocaleCacheManager::clearLocaleCaches()` uses `CacheTagsInvalidatorInterface::invalidateTags(['locale'])` only
- `ltools_update_load_language()` wrapper: `$mode` parameter replaced with `$overwrite` (bool)
- `ltools_add_translation()` wrapper: gains optional `$overwrite` parameter
- Composer package renamed from `revagomes/drupal-ltools` to `drupal/ltools`
- Added `php: ^8.1` constraint to `composer.json`

### Removed
- `LocaleApiAdapter` — called D7 private functions (`_locale_import_po`, `_locale_import_one_string_db`, `_locale_invalidate_js`) that do not exist in Drupal 10/11
