<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests that Drupal 10 static analysis tolerates the PHPUnit attributes.
 *
 * Rector writes `PHPUnit\Framework\Attributes\*` alongside the doc-comment
 * metadata it converts, and those classes first exist in PHPUnit 10. Drupal 10
 * resolves PHPUnit 9.6, so on a Drupal 10 build PHPStan reports each one as
 * `attribute.notFound`.
 *
 * The tests themselves still pass on that build, because PHP resolves
 * attribute classes lazily and PHPUnit 9.6 reads the doc-comment.
 * `phpstan.neon` therefore ignores that message.
 *
 * This test checks each attribute in the shipped tests against the
 * `phpstan.neon` ignore patterns. An unmatched attribute breaks the Drupal 10
 * lint, and without this test the failure appears only after a full Drupal 10
 * assemble in CI.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class PhpunitAttributesTest extends UnitTestCase {

  #[DataProvider('dataProviderShippedAttributesAreIgnored')]
  public function testShippedAttributesAreIgnored(string $path): void {
    $contents = file_get_contents($path);
    $this->assertIsString($contents);

    preg_match_all('/PHPUnit\\\\Framework\\\\Attributes\\\\(\w+)/', $contents, $matches);

    $patterns = self::ignorePatterns();
    $uncovered = [];

    foreach (array_unique($matches[1]) as $name) {
      // $message is the text PHPStan emits verbatim, so a pattern that stops
      // matching it is caught here, not on the next Drupal 10 build.
      $message = sprintf('Attribute class PHPUnit\Framework\Attributes\%s does not exist.', $name);

      foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message) === 1) {
          continue 2;
        }
      }

      $uncovered[] = $name;
    }

    $this->assertSame([], $uncovered, sprintf('%s uses PHPUnit attributes that no phpstan.neon ignoreErrors pattern matches: %s.', basename($path), implode(', ', $uncovered)));
  }

  public static function dataProviderShippedAttributesAreIgnored(): \Iterator {
    $directory = self::rootDir() . '/tests/src';

    $paths = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
      if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
        $paths[] = $file->getPathname();
      }
    }

    self::assertNotSame([], $paths, 'No test files found in tests/src.');

    sort($paths);

    foreach ($paths as $path) {
      yield basename($path) => ['path' => $path];
    }
  }

  /**
   * Get the message patterns PHPStan is configured to ignore.
   *
   * @return array<int, string>
   *   The patterns, in declaration order.
   */
  protected static function ignorePatterns(): array {
    // NEON is a superset of the YAML that `phpstan.neon` uses, and the parser
    // preserves the backslash escaping the patterns rely on.
    $parsed = Yaml::parseFile(self::rootDir() . '/phpstan.neon');

    $parameters = is_array($parsed) ? $parsed['parameters'] ?? NULL : NULL;
    $entries = is_array($parameters) ? $parameters['ignoreErrors'] ?? NULL : NULL;

    if (!is_array($entries)) {
      self::fail('phpstan.neon declares no ignoreErrors list.');
    }

    $patterns = [];

    // An entry is either a bare pattern or a mapping whose `messages` key
    // holds several.
    foreach ($entries as $entry) {
      $messages = is_array($entry) ? $entry['messages'] ?? NULL : $entry;

      foreach (is_array($messages) ? $messages : [$messages] as $message) {
        if (is_string($message)) {
          $patterns[] = $message;
        }
      }
    }

    return $patterns;
  }

  protected static function rootDir(): string {
    return dirname(__DIR__, 4);
  }

}
