<?php

namespace Drupal\Tests\ltools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Kernel tests for ltools translation services.
 *
 * @group ltools
 */
class LtoolsTranslationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['locale', 'language', 'ltools'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location']);
    $this->installConfig(['language']);
    $this->installEntitySchema('configurable_language');
  }

  /**
   * Tests importing a PO file creates the expected translation.
   */
  public function testImportPoFile(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $po_file = $this->writeTempPoFile('fr', ['Hello' => 'Bonjour']);

    $result = $this->container->get('ltools.translation_manager')
      ->importPoFile('fr', $po_file);

    $this->assertTrue($result);

    $storage = $this->container->get('locale.storage');
    $source = $storage->findString(['source' => 'Hello']);
    $this->assertNotNull($source);

    $translation = $storage->findTranslation(['lid' => $source->lid, 'language' => 'fr']);
    $this->assertNotNull($translation);
    $this->assertEquals('Bonjour', $translation->getString());
  }

  /**
   * Tests overwrite behaviour during PO file import.
   */
  public function testImportPoFileOverwrite(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $manager = $this->container->get('ltools.translation_manager');
    $storage = $this->container->get('locale.storage');

    $po_v1 = $this->writeTempPoFile('fr', ['Hello' => 'Bonjour'], 'v1');
    $manager->importPoFile('fr', $po_v1, FALSE, TRUE);

    $source = $storage->findString(['source' => 'Hello']);
    $this->assertEquals('Bonjour', $storage->findTranslation(['lid' => $source->lid, 'language' => 'fr'])->getString());

    $po_v2 = $this->writeTempPoFile('fr', ['Hello' => 'Salut'], 'v2');

    // overwrite=FALSE keeps original.
    $manager->importPoFile('fr', $po_v2, FALSE, FALSE);
    $source = $storage->findString(['source' => 'Hello']);
    $this->assertEquals('Bonjour', $storage->findTranslation(['lid' => $source->lid, 'language' => 'fr'])->getString());

    // overwrite=TRUE replaces.
    $manager->importPoFile('fr', $po_v2, FALSE, TRUE);
    $source = $storage->findString(['source' => 'Hello']);
    $this->assertEquals('Salut', $storage->findTranslation(['lid' => $source->lid, 'language' => 'fr'])->getString());
  }

  /**
   * Tests adding a single translation string.
   */
  public function testAddTranslation(): void {
    ConfigurableLanguage::createFromLangcode('de')->save();

    $this->container->get('ltools.translation_manager')
      ->addTranslation('Goodbye', 'Auf Wiedersehen', 'de');

    $storage = $this->container->get('locale.storage');
    $source = $storage->findString(['source' => 'Goodbye']);
    $this->assertNotNull($source);

    $translation = $storage->findTranslation(['lid' => $source->lid, 'language' => 'de']);
    $this->assertNotNull($translation);
    $this->assertEquals('Auf Wiedersehen', $translation->getString());
  }

  /**
   * Tests that overwrite=FALSE preserves customized strings.
   */
  public function testAddTranslationRespectOverwrite(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $manager = $this->container->get('ltools.translation_manager');
    $storage = $this->container->get('locale.storage');

    $manager->addTranslation('Thanks', 'Merci', 'fr', '', 'default', TRUE);

    $source = $storage->findString(['source' => 'Thanks']);
    $this->assertEquals('Merci', $storage->findTranslation(['lid' => $source->lid, 'language' => 'fr'])->getString());

    // overwrite=FALSE: existing customized string is preserved.
    $manager->addTranslation('Thanks', 'Gracias', 'fr', '', 'default', FALSE);
    $source = $storage->findString(['source' => 'Thanks']);
    $this->assertEquals('Merci', $storage->findTranslation(['lid' => $source->lid, 'language' => 'fr'])->getString());

    // overwrite=TRUE: string is replaced.
    $manager->addTranslation('Thanks', 'Gracias', 'fr', '', 'default', TRUE);
    $source = $storage->findString(['source' => 'Thanks']);
    $this->assertEquals('Gracias', $storage->findTranslation(['lid' => $source->lid, 'language' => 'fr'])->getString());
  }

  /**
   * Tests that findPoFiles returns only .po files, sorted.
   */
  public function testFindPoFiles(): void {
    $base = $this->siteDirectory . '/po-test';
    mkdir($base . '/sub', 0777, TRUE);

    file_put_contents($base . '/a.po', '');
    file_put_contents($base . '/b.txt', '');
    file_put_contents($base . '/sub/c.po', '');
    file_put_contents($base . '/sub/d.xml', '');

    $finder = $this->container->get('ltools.po_file_finder');

    $recursive = $finder->findPoFiles($base, TRUE);
    $this->assertCount(2, $recursive);
    $this->assertStringEndsWith('a.po', $recursive[0]);
    $this->assertStringEndsWith('c.po', $recursive[1]);

    $flat = $finder->findPoFiles($base, FALSE);
    $this->assertCount(1, $flat);
    $this->assertStringEndsWith('a.po', $flat[0]);
  }

  /**
   * Writes a temporary PO file and returns its path.
   *
   * @param string $langcode
   *   Language code for the PO header.
   * @param array<string, string> $strings
   *   Keyed by source string, value is translation.
   * @param string $suffix
   *   Optional filename suffix to allow multiple files per test.
   *
   * @return string
   *   Absolute path to the written file.
   */
  private function writeTempPoFile(string $langcode, array $strings, string $suffix = ''): string {
    $lines = [];
    $lines[] = 'msgid ""';
    $lines[] = 'msgstr ""';
    $lines[] = '"Content-Type: text/plain; charset=UTF-8\n"';
    $lines[] = '"Content-Transfer-Encoding: 8bit\n"';
    $lines[] = '"Language: ' . $langcode . '\n"';
    $lines[] = '';

    foreach ($strings as $source => $translation) {
      $lines[] = 'msgid "' . addcslashes($source, '"\\') . '"';
      $lines[] = 'msgstr "' . addcslashes($translation, '"\\') . '"';
      $lines[] = '';
    }

    $path = $this->siteDirectory . '/' . $langcode . ($suffix ? '-' . $suffix : '') . '.po';
    file_put_contents($path, implode("\n", $lines));
    return $path;
  }

}
