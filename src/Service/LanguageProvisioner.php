<?php

namespace Drupal\ltools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Creates languages on demand for translation import workflows.
 */
class LanguageProvisioner {

  /**
   * Constructs a LanguageProvisioner object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Ensures a language exists.
   *
   * @param array<string, mixed> $options
   *   Optional language options from the legacy wrapper API.
   */
  public function ensureLanguageExists(string $langcode, array $options = []): void {
    $language_storage = $this->entityTypeManager->getStorage('configurable_language');
    if ($language_storage->load($langcode) !== NULL) {
      return;
    }

    $direction = (int) ($options['direction'] ?? LanguageInterface::DIRECTION_LTR);
    $label = (string) ($options['name'] ?? $langcode);

    $language = $language_storage->create([
      'id' => $langcode,
      'label' => $label,
      'direction' => $direction,
      'weight' => 0,
      'locked' => FALSE,
    ]);

    $language->save();
  }

}
