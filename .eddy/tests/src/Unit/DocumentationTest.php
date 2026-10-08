<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that documentation stays consistent with the files it describes.
 *
 * A script can be added to the tooling package without a 'bin' entry, which
 * leaves it uninstalled, or without a row in the package README table.
 * A path can be copied into a PHPUnit config without being rebased onto the
 * build root.
 *
 * No such drift is reported by a linter, because each file stays individually
 * valid while the files disagree.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class DocumentationTest extends UnitTestCase {

  #[DataProvider('dataProviderToolingCommandIsDocumented')]
  public function testToolingCommandIsDocumented(string $name): void {
    $readme = file_get_contents(self::toolingDir() . '/README.md');
    $this->assertIsString($readme);

    // Anchored to the entry column: a name mentioned in another row's
    // description is a cross-reference, not an entry of its own.
    $documented = preg_match('/^\|\s*`' . preg_quote($name, '/') . '`\s*\|/m', $readme) === 1;

    $this->assertTrue($documented, sprintf('The tooling README has no table row for `%s`.', $name));
  }

  public static function dataProviderToolingCommandIsDocumented(): \Iterator {
    yield from self::toolingCommands();
  }

  #[DataProvider('dataProviderToolingCommandExists')]
  public function testToolingCommandExists(string $name): void {
    $this->assertFileExists(self::toolingDir() . '/src/' . $name, sprintf('The tooling composer.json declares the bin `%s`, which does not exist.', $name));
  }

  public static function dataProviderToolingCommandExists(): \Iterator {
    yield from self::toolingCommands();
  }

  #[DataProvider('dataProviderToolingScriptIsDeclared')]
  public function testToolingScriptIsDeclared(string $name): void {
    $names = array_column(iterator_to_array(self::toolingCommands()), 'name');

    $this->assertContains($name, $names, sprintf('The tooling composer.json does not declare `src/%s` as a bin, so Composer does not install it.', $name));
  }

  public static function dataProviderToolingScriptIsDeclared(): \Iterator {
    $paths = glob(self::toolingDir() . '/src/*') ?: [];

    self::assertNotSame([], $paths, 'No scripts found in the tooling package.');

    foreach ($paths as $path) {
      $name = basename($path);

      if ($name === 'helpers.php') {
        continue;
      }

      yield $name => ['name' => $name];
    }
  }

  #[DataProvider('dataProviderPhpunitCorePathsUseDocrootPrefix')]
  public function testPhpunitCorePathsUseDocrootPrefix(string $path): void {
    $contents = file_get_contents($path);
    $this->assertIsString($contents);

    // These configs are run from the build root, not the Drupal root, so the
    // docroot prefix in `bootstrap` applies to every other core-relative path.
    // Paths inside comments are included.
    preg_match('/bootstrap="([^"]+)"/', $contents, $bootstrap);
    $bootstrap_path = $bootstrap[1] ?? '';

    $this->assertNotSame('', $bootstrap_path, sprintf('%s declares no bootstrap attribute.', basename($path)));

    $position = strpos($bootstrap_path, 'core/');
    $this->assertIsInt($position, sprintf('%s bootstraps outside core: %s', basename($path), $bootstrap_path));

    $prefix = substr($bootstrap_path, 0, $position);

    $unprefixed = [];

    foreach (explode("\n", $contents) as $index => $line) {
      // Match each reference together with the path segments leading into it,
      // so the prefix can be compared by value. A lookbehind cannot serve
      // here: PCRE requires a fixed-length one, and the prefix is derived.
      preg_match_all('/[A-Za-z0-9_.\/-]*\bcore\//', $line, $references);

      $offenders = array_filter($references[0], static fn(string $reference): bool => !str_starts_with($reference, $prefix));

      foreach ($offenders as $offender) {
        $unprefixed[] = sprintf('line %d: %s', $index + 1, $offender);
      }
    }

    $this->assertSame([], $unprefixed, sprintf('%s references core paths without the `%s` docroot prefix.', basename($path), $prefix));
  }

  public static function dataProviderPhpunitCorePathsUseDocrootPrefix(): \Iterator {
    $paths = glob(self::rootDir() . '/phpunit*.xml') ?: [];

    self::assertNotSame([], $paths, 'No PHPUnit configuration files found in the project root.');

    foreach ($paths as $path) {
      yield basename($path) => ['path' => $path];
    }
  }

  /**
   * List the commands the tooling package installs.
   *
   * @return \Iterator<string, array{name: string}>
   *   The command names, keyed by name.
   */
  protected static function toolingCommands(): \Iterator {
    $manifest = json_decode((string) file_get_contents(self::toolingDir() . '/composer.json'), TRUE);
    $bins = is_array($manifest) && is_array($manifest['bin'] ?? NULL) ? $manifest['bin'] : [];

    self::assertNotSame([], $bins, 'The tooling composer.json declares no bins.');

    foreach ($bins as $bin) {
      self::assertIsString($bin);
      $name = basename($bin);

      yield $name => ['name' => $name];
    }
  }

  protected static function rootDir(): string {
    return dirname(__DIR__, 4);
  }

  protected static function toolingDir(): string {
    return self::rootDir() . '/.eddy/tooling';
  }

}
