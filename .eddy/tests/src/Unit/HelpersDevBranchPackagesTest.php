<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\dev_branch_packages;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the dev_branch_packages() helper.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\dev_branch_packages')]
#[Group('p0')]
final class HelpersDevBranchPackagesTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  /**
   * @param array<string, string> $expected
   */
  #[DataProvider('dataProviderDevBranchPackages')]
  public function testDevBranchPackages(?string $lock, array $expected): void {
    $lock_file = self::$tmp . '/lock_' . uniqid() . '/composer.lock';
    mkdir(dirname($lock_file), 0755, TRUE);

    if ($lock !== NULL) {
      file_put_contents($lock_file, $lock);
    }

    $this->assertSame($expected, dev_branch_packages($lock_file));
  }

  public static function dataProviderDevBranchPackages(): \Iterator {
    $lock = static fn(array $packages, array $packages_dev = []): string => json_encode([
      'packages' => array_map(static fn(string $name, string $version): array => ['name' => $name, 'version' => $version], array_keys($packages), $packages),
      'packages-dev' => array_map(static fn(string $name, string $version): array => ['name' => $name, 'version' => $version], array_keys($packages_dev), $packages_dev),
    ], JSON_THROW_ON_ERROR);

    yield 'branches in packages and packages-dev' => [
      $lock(['drupal/core' => '12.0.0-beta1', 'grasmash/yaml-cli' => '4.x-dev'], ['drush/drush' => '14.x-dev', 'phpunit/phpunit' => '12.5.3', 'vendor/default' => 'dev-main']),
      ['grasmash/yaml-cli' => '4.x-dev', 'drush/drush' => '14.x-dev', 'vendor/default' => 'dev-main'],
    ];
    yield 'releases only' => [$lock(['drupal/core' => '11.4.8'], ['drush/drush' => '13.8.0']), []];
    yield 'no packages-dev' => ['{"packages": [{"name": "drush/drush", "version": "14.x-dev"}]}', ['drush/drush' => '14.x-dev']];
    yield 'not JSON' => ['Not JSON', []];
    yield 'missing file' => [NULL, []];
  }

}
