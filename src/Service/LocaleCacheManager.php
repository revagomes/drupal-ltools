<?php

namespace Drupal\ltools\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Handles locale cache invalidation.
 */
class LocaleCacheManager {

  /**
   * Constructs a LocaleCacheManager object.
   */
  public function __construct(
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected LocaleApiAdapter $localeApiAdapter,
  ) {
  }

  /**
   * Clears locale-related caches.
   */
  public function clearLocaleCaches(): void {
    $this->localeApiAdapter->invalidateJavascriptCache();
    $this->cacheTagsInvalidator->invalidateTags(['locale']);
  }

}
