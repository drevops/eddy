<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\locked_packages;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the locked_packages() helper.
 */
#[CoversFunction('DrevOps\Eddy\Tooling\locked_packages')]
#[Group('p0')]
final class HelpersLockedPackagesTest extends UnitTestCase {

  /**
   * @param array<string, string> $expected
   */
  #[DataProvider('dataProviderLockedPackages')]
  public function testLockedPackages(?string $lock, string $field, array $expected): void {
    $lock_file = self::$tmp . '/lock_' . uniqid() . '/composer.lock';
    File::mkdir(dirname($lock_file), 0755);

    if ($lock !== NULL) {
      File::dump($lock_file, $lock);
    }

    $this->assertSame($expected, locked_packages($lock_file, $field));
  }

  public static function dataProviderLockedPackages(): \Iterator {
    $lock = json_encode([
      'packages' => [
        ['name' => 'drupal/bootstrap5', 'version' => '4.0.8', 'type' => 'drupal-theme'],
        ['name' => 'drupal/linkit', 'version' => '7.0.11', 'type' => 'drupal-module'],
      ],
      'packages-dev' => [
        ['name' => 'drush/drush', 'version' => '14.x-dev', 'type' => 'library'],
        ['name' => 'vendor/untyped', 'version' => '1.0.0'],
      ],
    ], JSON_THROW_ON_ERROR);

    yield 'types in packages and packages-dev' => [$lock, 'type', ['drupal/bootstrap5' => 'drupal-theme', 'drupal/linkit' => 'drupal-module', 'drush/drush' => 'library']];
    yield 'versions in packages and packages-dev' => [$lock, 'version', ['drupal/bootstrap5' => '4.0.8', 'drupal/linkit' => '7.0.11', 'drush/drush' => '14.x-dev', 'vendor/untyped' => '1.0.0']];
    yield 'field no package has' => [$lock, 'homepage', []];
    yield 'non-string value' => ['{"packages": [{"name": "drupal/bootstrap5", "type": ["drupal-theme"]}, {"name": "drupal/linkit", "type": "drupal-module"}]}', 'type', ['drupal/linkit' => 'drupal-module']];
    yield 'no packages-dev' => ['{"packages": [{"name": "drupal/bootstrap5", "type": "drupal-theme"}]}', 'type', ['drupal/bootstrap5' => 'drupal-theme']];
    yield 'packages-dev only' => ['{"packages-dev": [{"name": "drupal/bootstrap5", "type": "drupal-theme"}]}', 'type', ['drupal/bootstrap5' => 'drupal-theme']];
    yield 'malformed packages' => ['{"packages": "drupal/bootstrap5"}', 'type', []];
    yield 'not JSON' => ['Not JSON', 'type', []];
    yield 'missing file' => [NULL, 'type', []];
  }

}
