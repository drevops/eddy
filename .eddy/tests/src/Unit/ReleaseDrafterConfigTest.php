<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the version increment the release drafter configuration resolves.
 *
 * Release Drafter resolves the increment through `categories` and warns that
 * the top-level `version-resolver` block is deprecated.
 *
 * Once that block is removed upstream, a configuration still relying on it
 * silently falls back to the built-in `patch` increment. The next draft is
 * then named after the wrong release.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class ReleaseDrafterConfigTest extends UnitTestCase {

  public function testDeprecatedVersionResolverIsAbsent(): void {
    $this->assertArrayNotHasKey('version-resolver', self::config(), 'The `version-resolver` block is deprecated: version resolution belongs in `categories`.');
  }

  public function testResolvedIncrementIsMinor(): void {
    $categories = self::config()['categories'] ?? NULL;

    if (!is_array($categories)) {
      self::fail('The release drafter configuration declares no `categories`.');
    }

    $increments = [];

    foreach ($categories as $category) {
      if (!is_array($category)) {
        continue;
      }

      if (($category['type'] ?? NULL) !== 'version-resolver') {
        continue;
      }

      // A category that carries no condition matches every change, so it holds
      // the increment that applies when no other category does. An empty
      // `when` list expresses the same thing: Release Drafter matches every
      // change once the parsed condition list is empty.
      if (($category['when'] ?? []) !== []) {
        continue;
      }

      // Release Drafter assumes `patch` for a category that names no
      // increment.
      $increments[] = $category['semver-increment'] ?? 'patch';
    }

    $this->assertSame(['minor'], $increments, 'Exactly one `version-resolver` category with no `when` condition must resolve the "minor" increment.');
  }

  /**
   * Read the release drafter configuration.
   *
   * @return array<mixed, mixed>
   *   The parsed configuration.
   */
  protected static function config(): array {
    $path = dirname(__DIR__, 4) . '/.github/release-drafter.yml';
    $parsed = Yaml::parse(File::read($path));

    if (!is_array($parsed)) {
      self::fail(sprintf('%s does not parse to a mapping.', basename($path)));
    }

    return $parsed;
  }

}
