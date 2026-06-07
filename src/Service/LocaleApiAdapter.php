<?php

namespace Drupal\ltools\Service;

/**
 * Wraps locale private API calls behind a single service boundary.
 *
 * Locale import internals remain underscore-prefixed in core. This adapter
 * isolates those calls so future core API changes are localized.
 */
class LocaleApiAdapter {

  /**
   * Imports a PO file using locale's current import implementation.
   */
  public function importPoFile(string $langcode, string $poFile, int $mode, string $group): bool {
    if (!function_exists('_locale_import_po')) {
      $locale_module = DRUPAL_ROOT . '/core/modules/locale/locale.module';
      if (is_file($locale_module)) {
        require_once $locale_module;
      }
    }

    if (!function_exists('_locale_import_po')) {
      throw new \RuntimeException('Locale PO import API is unavailable.');
    }

    $file = (object) [
      'filepath' => $poFile,
      'filename' => basename($poFile),
    ];

    return (bool) _locale_import_po($file, $langcode, $mode, $group);
  }

  /**
   * Imports one string into locale storage.
   *
   * @param array<string, int> $report
   *   Mutable report array expected by locale import internals.
   */
  public function importOneString(array &$report, string $langcode, string $context, string $source, string $translation, string $textgroup, int $mode): void {
    if (!function_exists('_locale_import_one_string_db')) {
      $locale_bulk_inc = DRUPAL_ROOT . '/core/modules/locale/locale.bulk.inc';
      if (is_file($locale_bulk_inc)) {
        require_once $locale_bulk_inc;
      }
    }

    if (!function_exists('_locale_import_one_string_db')) {
      throw new \RuntimeException('Locale single-string import API is unavailable.');
    }

    _locale_import_one_string_db(
      $report,
      $langcode,
      $context,
      $source,
      $translation,
      $textgroup,
      sprintf('Manual import via helper %s().', __METHOD__),
      $mode,
    );
  }

  /**
   * Invalidates JavaScript locale caches where supported.
   */
  public function invalidateJavascriptCache(): void {
    if (!function_exists('_locale_invalidate_js')) {
      $locale_module = DRUPAL_ROOT . '/core/modules/locale/locale.module';
      if (is_file($locale_module)) {
        require_once $locale_module;
      }
    }

    if (function_exists('_locale_invalidate_js')) {
      _locale_invalidate_js();
    }
  }

}
