# drupal.org Publication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix broken D10/11 runtime APIs, remove LocaleApiAdapter, add tests, and complete all drupal.org packaging requirements.

**Architecture:** Delete `LocaleApiAdapter` (wrapped D7 private functions that don't exist in D10/11); rewrite `TranslationManager` to use `Drupal\locale\Gettext::fileToDatabase()` and `locale.storage` (`StringStorageInterface`); simplify `LocaleCacheManager` to a single cache tag invalidation; add one `KernelTestBase` class for end-to-end service coverage.

**Tech Stack:** Drupal 10.3+/11, `locale.storage` (`StringStorageInterface`), `Drupal\locale\Gettext`, `CacheTagsInvalidatorInterface`, PHPUnit/KernelTestBase.

---

## File Map

| File | Action | Reason |
|---|---|---|
| `src/Service/LocaleApiAdapter.php` | **Delete** | Wrapped D7 internals — no longer needed |
| `src/Service/LocaleCacheManager.php` | **Modify** | Remove adapter dep; one-line cache tag invalidation |
| `src/Service/TranslationManager.php` | **Rewrite** | Use `StringStorageInterface` + `Gettext::fileToDatabase()` |
| `ltools.services.yml` | **Modify** | Remove adapter service; update constructor args |
| `ltools.module` | **Modify** | Update wrappers to new signatures |
| `composer.json` | **Modify** | Rename to `drupal/ltools`; add `php: ^8.1` |
| `LICENSE.txt` | **Create** | Required by drupal.org project review |
| `.gitignore` | **Create** | Exclude `/vendor/` |
| `tests/src/Kernel/LtoolsTranslationTest.php` | **Create** | KernelTestBase for all services |
| `README.md` | **Modify** | Updated install command and API signatures |
| `CLAUDE.md` | **Modify** | Reflect LocaleApiAdapter removal and new signatures |

---

## Task 1: Packaging fixes

**Files:**
- Modify: `composer.json`
- Create: `LICENSE.txt`
- Create: `.gitignore`

- [ ] **Step 1: Update composer.json**

Replace the full contents of `composer.json` with:

```json
{
  "name": "drupal/ltools",
  "description": "Locale helper utilities for Drupal.",
  "type": "drupal-module",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": "^8.1",
    "drupal/core": "^10.3 || ^11"
  },
  "require-dev": {
    "drupal/coder": "^8.3"
  },
  "autoload": {
    "psr-4": {
      "Drupal\\ltools\\": "src/"
    }
  }
}
```

- [ ] **Step 2: Create LICENSE.txt**

Create `LICENSE.txt` with the GNU GPL-2.0-or-later text. Copy the exact content from any drupal.org module's LICENSE.txt (they are all identical), or download from:

```
https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
```

The file must begin with:
```
GNU GENERAL PUBLIC LICENSE
   Version 2, June 1991
```

- [ ] **Step 3: Create .gitignore**

Create `.gitignore`:

```
/vendor/
```

- [ ] **Step 4: Verify composer.json is valid**

```bash
composer validate
```

Expected output: `./composer.json is valid`

- [ ] **Step 5: Commit**

```bash
git add composer.json LICENSE.txt .gitignore
git commit -m "Add packaging files for drupal.org: rename package, LICENSE.txt, .gitignore"
```

---

## Task 2: Simplify LocaleCacheManager

**Files:**
- Modify: `src/Service/LocaleCacheManager.php`

- [ ] **Step 1: Rewrite LocaleCacheManager**

Replace the full contents of `src/Service/LocaleCacheManager.php`:

```php
<?php

namespace Drupal\ltools\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Handles locale cache invalidation.
 */
class LocaleCacheManager {

  public function __construct(
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {
  }

  /**
   * Clears locale-related caches.
   */
  public function clearLocaleCaches(): void {
    $this->cacheTagsInvalidator->invalidateTags(['locale']);
  }

}
```

- [ ] **Step 2: Commit**

```bash
git add src/Service/LocaleCacheManager.php
git commit -m "Simplify LocaleCacheManager: drop LocaleApiAdapter dep, use cache tag invalidation only"
```

---

## Task 3: Rewrite TranslationManager with D10/11 APIs

**Files:**
- Modify: `src/Service/TranslationManager.php`

- [ ] **Step 1: Rewrite TranslationManager**

Replace the full contents of `src/Service/TranslationManager.php`:

```php
<?php

namespace Drupal\ltools\Service;

use Drupal\locale\Gettext;
use Drupal\locale\StringStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * Main orchestration service for translation workflows.
 */
class TranslationManager {

  public function __construct(
    protected StringStorageInterface $localeStorage,
    protected LanguageProvisioner $languageProvisioner,
    protected LocaleCacheManager $localeCacheManager,
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Imports translations from a PO file.
   *
   * @param bool $overwrite
   *   Overwrite both customized and non-customized existing translations.
   * @param array<string, mixed> $addLanguageOptions
   *   Optional language options used when $newLanguage is TRUE.
   */
  public function importPoFile(
    string $langcode,
    string $poFile,
    bool $newLanguage = FALSE,
    bool $overwrite = TRUE,
    string $group = 'default',
    array $addLanguageOptions = [],
  ): bool {
    if (!is_file($poFile) || !is_readable($poFile)) {
      $this->logger->error('PO import failed for language {langcode}. File not readable: {file}', [
        'langcode' => $langcode,
        'file' => $poFile,
      ]);
      return FALSE;
    }

    if ($newLanguage) {
      $this->languageProvisioner->ensureLanguageExists($langcode, $addLanguageOptions);
    }

    $file = new \stdClass();
    $file->langcode = $langcode;
    $file->uri = $poFile;
    $file->filename = basename($poFile);

    $options = [
      'overwrite_options' => [
        'not_customized' => $overwrite,
        'customized' => $overwrite,
      ],
      'customized' => LOCALE_NOT_CUSTOMIZED,
    ];

    $result = Gettext::fileToDatabase($file, $options);

    if ($result !== FALSE) {
      $this->logger->notice('PO import completed for {langcode}: {file}', [
        'langcode' => $langcode,
        'file' => $poFile,
      ]);
      return TRUE;
    }

    $this->logger->warning('PO import failed for {langcode}: {file}', [
      'langcode' => $langcode,
      'file' => $poFile,
    ]);
    return FALSE;
  }

  /**
   * Adds one translation string to locale storage.
   *
   * @param bool $overwrite
   *   If FALSE, skips strings already marked as customized in locale storage.
   */
  public function addTranslation(
    string $source,
    string $translation,
    string $langcode,
    string $context = '',
    string $textgroup = 'default',
    bool $overwrite = TRUE,
  ): void {
    $string = $this->localeStorage->findString(['source' => $source, 'context' => $context]);
    if (!$string) {
      $string = $this->localeStorage->createString([
        'source' => $source,
        'context' => $context,
      ]);
      $this->localeStorage->save($string);
    }

    $existing = $this->localeStorage->findTranslation([
      'lid' => $string->lid,
      'language' => $langcode,
    ]);

    if ($existing && !$overwrite && $existing->customized == LOCALE_CUSTOMIZED) {
      return;
    }

    if ($existing) {
      $existing->setString($translation);
      $existing->customized = LOCALE_CUSTOMIZED;
      $this->localeStorage->save($existing);
    }
    else {
      $newTranslation = $this->localeStorage->createTranslation([
        'lid' => $string->lid,
        'language' => $langcode,
        'translation' => $translation,
        'customized' => LOCALE_CUSTOMIZED,
      ]);
      $this->localeStorage->save($newTranslation);
    }

    $this->localeCacheManager->clearLocaleCaches();
  }

}
```

- [ ] **Step 2: Commit**

```bash
git add src/Service/TranslationManager.php
git commit -m "Rewrite TranslationManager: use Gettext::fileToDatabase() and locale.storage (D10/11 APIs)"
```

---

## Task 4: Delete LocaleApiAdapter and update ltools.services.yml

**Files:**
- Delete: `src/Service/LocaleApiAdapter.php`
- Modify: `ltools.services.yml`

- [ ] **Step 1: Delete LocaleApiAdapter**

```bash
git rm src/Service/LocaleApiAdapter.php
```

- [ ] **Step 2: Rewrite ltools.services.yml**

Replace the full contents of `ltools.services.yml`:

```yaml
services:
  logger.channel.ltools:
    parent: logger.channel_base
    arguments: ['ltools']

  ltools.po_file_finder:
    class: Drupal\ltools\Service\PoFileFinder

  ltools.locale_cache_manager:
    class: Drupal\ltools\Service\LocaleCacheManager
    arguments:
      - '@cache_tags.invalidator'

  ltools.language_provisioner:
    class: Drupal\ltools\Service\LanguageProvisioner
    arguments:
      - '@entity_type.manager'

  ltools.translation_manager:
    class: Drupal\ltools\Service\TranslationManager
    arguments:
      - '@locale.storage'
      - '@ltools.language_provisioner'
      - '@ltools.locale_cache_manager'
      - '@logger.channel.ltools'
```

- [ ] **Step 3: Commit**

```bash
git add ltools.services.yml
git commit -m "Remove LocaleApiAdapter service; update TranslationManager and LocaleCacheManager wiring"
```

---

## Task 5: Update ltools.module wrappers

**Files:**
- Modify: `ltools.module`

The `ltools_update_load_language()` wrapper drops the `$mode` integer param (mapped to D7 constants) and replaces it with a `bool $overwrite`. The `ltools_add_translation()` wrapper gains `$overwrite = TRUE`.

- [ ] **Step 1: Rewrite ltools.module**

Replace the full contents of `ltools.module`:

```php
<?php

/**
 * @file
 * Backward-compatible procedural wrappers for Locale Tools services.
 */

/**
 * Imports translations from a PO file.
 *
 * @param string $langcode
 *   The language code (for example: fr, de, it).
 * @param string $po_file
 *   Absolute path to a .po file.
 * @param bool $new_lang
 *   Whether to create the language if it does not exist.
 * @param bool $overwrite
 *   Whether to overwrite existing translations. Defaults to TRUE.
 * @param string $group
 *   Text group to import into.
 * @param array<string, mixed> $add_language_options
 *   Optional language creation options passed to LanguageProvisioner.
 *
 * @return bool
 *   TRUE on success, FALSE otherwise.
 */
function ltools_update_load_language($langcode, $po_file, $new_lang = FALSE, $overwrite = TRUE, $group = 'default', $add_language_options = []) {
  return \Drupal::service('ltools.translation_manager')->importPoFile(
    (string) $langcode,
    (string) $po_file,
    (bool) $new_lang,
    (bool) $overwrite,
    (string) $group,
    (array) $add_language_options,
  );
}

/**
 * Finds PO files under a given path.
 *
 * @param string $path
 *   Base path.
 * @param bool $recursive
 *   Whether to scan recursively.
 *
 * @return string[]
 *   Full paths to detected .po files.
 */
function ltools_find_po_files($path, $recursive = TRUE) {
  return \Drupal::service('ltools.po_file_finder')->findPoFiles((string) $path, (bool) $recursive);
}

/**
 * Adds a single translation to locale storage.
 *
 * @param string $source
 *   Source string.
 * @param string $translation
 *   Translated string.
 * @param string $langcode
 *   Language code.
 * @param string $context
 *   Translation context.
 * @param string $textgroup
 *   Translation textgroup.
 * @param bool $overwrite
 *   Whether to overwrite existing customized translations. Defaults to TRUE.
 */
function ltools_add_translation($source, $translation, $langcode, $context = '', $textgroup = 'default', $overwrite = TRUE) {
  \Drupal::service('ltools.translation_manager')->addTranslation(
    (string) $source,
    (string) $translation,
    (string) $langcode,
    (string) $context,
    (string) $textgroup,
    (bool) $overwrite,
  );
}

/**
 * Clears locale-related caches.
 */
function ltools_clear_cache() {
  \Drupal::service('ltools.locale_cache_manager')->clearLocaleCaches();
}
```

- [ ] **Step 2: Commit**

```bash
git add ltools.module
git commit -m "Update ltools.module wrappers: replace \$mode with \$overwrite bool, add \$overwrite to ltools_add_translation()"
```

---

## Task 6: Write KernelTests

**Files:**
- Create: `tests/src/Kernel/LtoolsTranslationTest.php`

- [ ] **Step 1: Create the tests directory**

```bash
mkdir -p tests/src/Kernel
```

- [ ] **Step 2: Create LtoolsTranslationTest.php**

Create `tests/src/Kernel/LtoolsTranslationTest.php`:

```php
<?php

namespace Drupal\Tests\ltools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ltools\Service\PoFileFinder;
use Drupal\ltools\Service\TranslationManager;
use Drupal\locale\StringStorageInterface;

/**
 * Kernel tests for Locale Tools services.
 *
 * @group ltools
 */
class LtoolsTranslationTest extends KernelTestBase {

  protected static $modules = ['locale', 'language', 'ltools'];

  protected TranslationManager $translationManager;
  protected StringStorageInterface $localeStorage;
  protected PoFileFinder $poFileFinder;

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location']);
    $this->installEntitySchema('configurable_language');
    $this->translationManager = $this->container->get('ltools.translation_manager');
    $this->localeStorage = $this->container->get('locale.storage');
    $this->poFileFinder = $this->container->get('ltools.po_file_finder');
  }

  /**
   * Tests PO file import creates expected source string and translation.
   */
  public function testImportPoFile(): void {
    $poFile = $this->writeTempPoFile("msgid \"Hello\"\nmsgstr \"Bonjour\"\n");

    try {
      $result = $this->translationManager->importPoFile('fr', $poFile, TRUE);
      $this->assertTrue($result);

      $string = $this->localeStorage->findString(['source' => 'Hello']);
      $this->assertNotFalse($string, 'Source string "Hello" was saved to locale storage.');

      $translation = $this->localeStorage->findTranslation([
        'lid' => $string->lid,
        'language' => 'fr',
      ]);
      $this->assertNotFalse($translation, 'French translation was saved.');
      $this->assertEquals('Bonjour', $translation->getString());
    }
    finally {
      @unlink($poFile);
    }
  }

  /**
   * Tests overwrite=FALSE preserves existing translation; overwrite=TRUE replaces it.
   */
  public function testImportPoFileOverwrite(): void {
    $firstPo = $this->writeTempPoFile("msgid \"Hello\"\nmsgstr \"Bonjour\"\n");
    $secondPo = $this->writeTempPoFile("msgid \"Hello\"\nmsgstr \"Salut\"\n");

    try {
      // Import first translation.
      $this->translationManager->importPoFile('fr', $firstPo, TRUE);

      // Import with overwrite=FALSE — should keep "Bonjour".
      $this->translationManager->importPoFile('fr', $secondPo, FALSE, FALSE);
      $string = $this->localeStorage->findString(['source' => 'Hello']);
      $translation = $this->localeStorage->findTranslation(['lid' => $string->lid, 'language' => 'fr']);
      $this->assertEquals('Bonjour', $translation->getString(), 'Translation not overwritten when overwrite=FALSE.');

      // Import with overwrite=TRUE — should replace with "Salut".
      $this->translationManager->importPoFile('fr', $secondPo, FALSE, TRUE);
      $translation = $this->localeStorage->findTranslation(['lid' => $string->lid, 'language' => 'fr']);
      $this->assertEquals('Salut', $translation->getString(), 'Translation replaced when overwrite=TRUE.');
    }
    finally {
      @unlink($firstPo);
      @unlink($secondPo);
    }
  }

  /**
   * Tests addTranslation creates source string and translation in locale storage.
   */
  public function testAddTranslation(): void {
    $this->createLanguage('fr');

    $this->translationManager->addTranslation('Goodbye', 'Au revoir', 'fr');

    $string = $this->localeStorage->findString(['source' => 'Goodbye']);
    $this->assertNotFalse($string, 'Source string "Goodbye" was saved.');

    $translation = $this->localeStorage->findTranslation(['lid' => $string->lid, 'language' => 'fr']);
    $this->assertNotFalse($translation, 'French translation was saved.');
    $this->assertEquals('Au revoir', $translation->getString());
  }

  /**
   * Tests addTranslation respects overwrite=FALSE for customized strings.
   */
  public function testAddTranslationRespectOverwrite(): void {
    $this->createLanguage('fr');

    // Add initial customized translation.
    $this->translationManager->addTranslation('Goodbye', 'Au revoir', 'fr', '', 'default', TRUE);

    // Call again with overwrite=FALSE — customized string must be preserved.
    $this->translationManager->addTranslation('Goodbye', 'Adieu', 'fr', '', 'default', FALSE);

    $string = $this->localeStorage->findString(['source' => 'Goodbye']);
    $translation = $this->localeStorage->findTranslation(['lid' => $string->lid, 'language' => 'fr']);
    $this->assertEquals('Au revoir', $translation->getString(), 'Customized translation preserved when overwrite=FALSE.');
  }

  /**
   * Tests findPoFiles returns only .po files, sorted, respecting recursion flag.
   */
  public function testFindPoFiles(): void {
    $dir = sys_get_temp_dir() . '/ltools_test_' . uniqid();
    mkdir($dir . '/subdir', 0777, TRUE);
    file_put_contents($dir . '/fr.po', '');
    file_put_contents($dir . '/en.po', '');
    file_put_contents($dir . '/subdir/de.po', '');
    file_put_contents($dir . '/subdir/README.txt', '');

    try {
      // Recursive: finds all three .po files, sorted by full path.
      $all = $this->poFileFinder->findPoFiles($dir, TRUE);
      $this->assertCount(3, $all);
      $this->assertStringEndsWith('en.po', $all[0]);
      $this->assertStringEndsWith('fr.po', $all[1]);
      $this->assertStringEndsWith('de.po', $all[2]);

      // Non-recursive: only the two top-level .po files.
      $topOnly = $this->poFileFinder->findPoFiles($dir, FALSE);
      $this->assertCount(2, $topOnly);
      $this->assertStringEndsWith('en.po', $topOnly[0]);
      $this->assertStringEndsWith('fr.po', $topOnly[1]);

      // Non-existent directory returns empty array.
      $this->assertSame([], $this->poFileFinder->findPoFiles('/nonexistent/path'));
    }
    finally {
      @unlink($dir . '/fr.po');
      @unlink($dir . '/en.po');
      @unlink($dir . '/subdir/de.po');
      @unlink($dir . '/subdir/README.txt');
      @rmdir($dir . '/subdir');
      @rmdir($dir);
    }
  }

  /**
   * Writes a minimal PO file with standard headers to a temp path.
   */
  private function writeTempPoFile(string $entries): string {
    $header = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n\n";
    $path = tempnam(sys_get_temp_dir(), 'ltools_') . '.po';
    file_put_contents($path, $header . $entries);
    return $path;
  }

  /**
   * Creates a configurable language entity.
   */
  private function createLanguage(string $langcode): void {
    $this->container->get('entity_type.manager')
      ->getStorage('configurable_language')
      ->create(['id' => $langcode, 'label' => strtoupper($langcode)])
      ->save();
  }

}
```

- [ ] **Step 3: Commit**

```bash
git add tests/
git commit -m "Add KernelTestBase coverage for TranslationManager and PoFileFinder"
```

---

## Task 7: Update README.md and CLAUDE.md

**Files:**
- Modify: `README.md`
- Modify: `CLAUDE.md`

- [ ] **Step 1: Rewrite README.md**

Replace the full contents of `README.md`:

```markdown
# Locale Tools (`ltools`)

Drupal utility module for locale import workflows.

## Compatibility

- Drupal 10.3+
- Drupal 11
- PHP 8.1+

## Installation

```bash
composer require drupal/ltools
drush en ltools -y
```

## Services

### `ltools.translation_manager`

Import PO files and add single translation strings.

```php
// Import a .po file (creates language 'fr' if it does not exist)
\Drupal::service('ltools.translation_manager')
  ->importPoFile('fr', '/path/to/fr.po', TRUE);

// Import without overwriting existing translations
\Drupal::service('ltools.translation_manager')
  ->importPoFile('fr', '/path/to/fr.po', FALSE, FALSE);

// Add a single translation string
\Drupal::service('ltools.translation_manager')
  ->addTranslation('Hello', 'Bonjour', 'fr');

// Add without overwriting customized strings
\Drupal::service('ltools.translation_manager')
  ->addTranslation('Hello', 'Bonjour', 'fr', '', 'default', FALSE);
```

### `ltools.po_file_finder`

Find `.po` files under a directory.

```php
$files = \Drupal::service('ltools.po_file_finder')
  ->findPoFiles('/path/to/translations', TRUE);
```

### `ltools.locale_cache_manager`

Clear locale-related caches.

```php
\Drupal::service('ltools.locale_cache_manager')->clearLocaleCaches();
```

## Backward-compatible procedural wrappers

The following functions remain available for existing callers:

- `ltools_update_load_language($langcode, $po_file, $new_lang, $overwrite, $group, $options)`
- `ltools_find_po_files($path, $recursive)`
- `ltools_add_translation($source, $translation, $langcode, $context, $textgroup, $overwrite)`
- `ltools_clear_cache()`

## Architecture

`TranslationManager` uses `Drupal\locale\Gettext::fileToDatabase()` for PO imports and the `locale.storage` service (`StringStorageInterface`) for single-string imports. No internal Drupal locale functions are called.
```

- [ ] **Step 2: Rewrite CLAUDE.md**

Replace the full contents of `CLAUDE.md`:

```markdown
# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ltools** (Locale Tools) is a Drupal module that wraps the `locale` module's APIs to simplify programmatic import and management of translations. It is a dev-tool/utility library, not a user-facing feature module.

- Drupal core compatibility: `^10.3 || ^11`
- Depends on: `locale` (Drupal core)
- Package: Dev tool suite

## Module Structure

```
ltools.info.yml          — module metadata
ltools.module            — thin backward-compatible wrappers
ltools.services.yml      — service definitions
src/Service/
  TranslationManager.php   — PO import (Gettext::fileToDatabase) + single-string import (locale.storage)
  PoFileFinder.php         — recursive/non-recursive .po discovery
  LocaleCacheManager.php   — locale cache tag invalidation
  LanguageProvisioner.php  — on-demand language entity creation
composer.json            — Composer package definition (drupal/ltools, PSR-4 autoloading)
.github/workflows/ci.yml — CI: composer validate, PHP lint, PHPCS, PHPUnit
tests/src/Kernel/        — KernelTestBase coverage
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

// Add a single translation string ($overwrite defaults to TRUE)
\Drupal::service('ltools.translation_manager')
  ->addTranslation('Hello', 'Bonjour', 'fr');

// Clear locale cache
\Drupal::service('ltools.locale_cache_manager')->clearLocaleCaches();
```

### Via legacy wrappers in `ltools.module` (backward compatibility)

`ltools_update_load_language()`, `ltools_find_po_files()`, `ltools_add_translation()`, `ltools_clear_cache()` — these delegate to the services above.

## Architecture Notes

`TranslationManager` uses only public Drupal 10/11 APIs:
- `Drupal\locale\Gettext::fileToDatabase()` for PO file imports
- `locale.storage` (`StringStorageInterface`) for single-string inserts/updates

There is no `LocaleApiAdapter` — the isolation layer was removed when the module moved to public D10/11 APIs.

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
```

- [ ] **Step 3: Commit**

```bash
git add README.md CLAUDE.md
git commit -m "Update README.md and CLAUDE.md: reflect D10/11 APIs, new signatures, removed LocaleApiAdapter"
```

---

## Task 8: Push and verify CI

- [ ] **Step 1: Push to GitHub**

```bash
git push origin master
```

- [ ] **Step 2: Watch CI run**

```bash
gh run watch --repo revagomes/drupal-ltools
```

Expected: all steps green (composer validate, PHP lint, PHPCS with Drupal standards since `drupal/coder` is now in `require-dev`).

- [ ] **Step 3: Tag new release**

```bash
git tag -a 2.1.0 -m "Fix D10/11 runtime APIs, add tests, complete drupal.org packaging"
git push origin 2.1.0
```

- [ ] **Step 4: Create GitHub release**

```bash
gh release create 2.1.0 \
  --repo revagomes/drupal-ltools \
  --title "2.1.0 — drupal.org ready" \
  --notes "Fix broken D10/11 locale APIs, add KernelTest coverage, complete packaging (LICENSE.txt, .gitignore, package rename to drupal/ltools)."
```
