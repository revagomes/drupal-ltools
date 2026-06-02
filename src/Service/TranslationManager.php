<?php

namespace Drupal\ltools\Service;

use Psr\Log\LoggerInterface;

/**
 * Main orchestration service for translation workflows.
 */
class TranslationManager {

  /**
   * Constructs a TranslationManager object.
   */
  public function __construct(
    protected LanguageProvisioner $languageProvisioner,
    protected LocaleApiAdapter $localeApiAdapter,
    protected LocaleCacheManager $localeCacheManager,
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Imports translations from a PO file.
   *
   * @param array<string, mixed> $addLanguageOptions
   *   Optional language options used when creating a language.
   *   Existing configured languages are preserved and not modified.
   */
  public function importPoFile(string $langcode, string $poFile, bool $newLanguage = FALSE, ?int $mode = NULL, string $group = 'default', array $addLanguageOptions = []): bool {
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

    $effectiveMode = $mode ?? (defined('LOCALE_IMPORT_OVERWRITE') ? LOCALE_IMPORT_OVERWRITE : 0);
    $result = $this->localeApiAdapter->importPoFile($langcode, $poFile, $effectiveMode, $group);

    if ($result) {
      $this->logger->notice('PO import completed for {langcode}: {file}', [
        'langcode' => $langcode,
        'file' => $poFile,
      ]);
    }
    else {
      $this->logger->warning('PO import failed for {langcode}: {file}', [
        'langcode' => $langcode,
        'file' => $poFile,
      ]);
    }

    return $result;
  }

  /**
   * Adds one translation and clears locale caches.
   */
  public function addTranslation(string $source, string $translation, string $langcode, string $context = '', string $textgroup = 'default'): void {
    $report = ['additions' => 0, 'updates' => 0, 'deletes' => 0, 'skips' => 0];
    $mode = defined('LOCALE_IMPORT_OVERWRITE') ? LOCALE_IMPORT_OVERWRITE : 0;

    $this->localeApiAdapter->importOneString(
      $report,
      $langcode,
      $context,
      $source,
      $translation,
      $textgroup,
      $mode,
    );

    $this->localeCacheManager->clearLocaleCaches();
  }

}
