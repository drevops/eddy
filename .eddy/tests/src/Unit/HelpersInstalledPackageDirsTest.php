<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\installed_package_dirs;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\Tooling\installed_package_dirs')]
#[Group('p0')]
final class HelpersInstalledPackageDirsTest extends UnitTestCase {

  /**
   * @param array<string, string> $expected
   *   The directories relative to the vendor directory, keyed by package.
   */
  #[DataProvider('dataProviderInstalledPackageDirs')]
  public function testInstalledPackageDirs(?string $installed, array $expected): void {
    $vendor_dir = self::$tmp . '/vendor_' . uniqid();

    if ($installed !== NULL) {
      File::dump($vendor_dir . '/composer/installed.json', $installed);
    }

    $expected = array_map(static fn(string $dir): string => $vendor_dir . '/' . $dir, $expected);

    $this->assertSame($expected, installed_package_dirs($vendor_dir));
  }

  public static function dataProviderInstalledPackageDirs(): \Iterator {
    $installed = json_encode([
      'packages' => [
        ['name' => 'consolidation/robo', 'install-path' => '../consolidation/robo'],
        ['name' => 'drupal/sdc_devel', 'install-path' => '../../web/modules/contrib/sdc_devel'],
      ],
    ], JSON_THROW_ON_ERROR);

    yield 'vendor package and Drupal module' => [$installed, ['consolidation/robo' => 'composer/../consolidation/robo', 'drupal/sdc_devel' => 'composer/../../web/modules/contrib/sdc_devel']];
    yield 'package without an install path' => ['{"packages": [{"name": "drupal/core-recommended"}, {"name": "drupal/core", "install-path": "../../web/core"}]}', ['drupal/core' => 'composer/../../web/core']];
    yield 'non-string values' => ['{"packages": [{"name": ["drupal/core"], "install-path": "../../web/core"}, {"name": "drush/drush", "install-path": null}]}', []];
    yield 'non-array package' => ['{"packages": ["drupal/core"]}', []];
    yield 'no packages' => ['{"dev": true}', []];
    yield 'not JSON' => ['Not JSON', []];
    yield 'missing file' => [NULL, []];
  }

}
