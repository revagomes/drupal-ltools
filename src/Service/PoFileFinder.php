<?php

namespace Drupal\ltools\Service;

/**
 * Finds PO files in a given filesystem path.
 */
class PoFileFinder {

  /**
   * Finds all .po files under a directory.
   *
   * @return string[]
   *   Deterministically sorted absolute file paths.
   */
  public function findPoFiles(string $path, bool $recursive = TRUE): array {
    $basePath = rtrim($path, DIRECTORY_SEPARATOR);
    if ($basePath === '' || !is_dir($basePath)) {
      return [];
    }

    $files = [];

    if ($recursive) {
      $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS)
      );
    }
    else {
      $iterator = new \FilesystemIterator($basePath, \FilesystemIterator::SKIP_DOTS);
    }

    foreach ($iterator as $fileInfo) {
      if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
        continue;
      }
      if (strtolower($fileInfo->getExtension()) !== 'po') {
        continue;
      }
      $files[] = $fileInfo->getPathname();
    }

    sort($files);
    return $files;
  }

}
