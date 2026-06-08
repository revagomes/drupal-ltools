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
