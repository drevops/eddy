<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\drupal_release_select;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the drupal_release_select() helper.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\drupal_release_select')]
#[Group('p0')]
final class HelpersDrupalReleaseSelectTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  /**
   * @param array<int, string> $versions
   */
  #[DataProvider('dataProviderDrupalReleaseSelect')]
  public function testDrupalReleaseSelect(string $constraint, array $versions, ?string $expected): void {
    $this->assertSame($expected, drupal_release_select($constraint, $versions));
  }

  public static function dataProviderDrupalReleaseSelect(): \Iterator {
    // Drupal 12 has pre-releases only, and Drupal 11.5 a development branch.
    $current = ['12.0.x-dev', '12.0.0-beta1', '12.0.0-alpha1', '11.x-dev', '11.5.x-dev', '11.4.x-dev', '11.4.8', '11.4.0', '11.4.0-rc2', '11.4.0-beta1', '11.1.x-dev', '11.1.10', '11.1.0', '10.6.x-dev', '10.6.18', 'dev-main'];
    // Drupal 12.0 is stable and Drupal 12.1 has pre-releases.
    $later = ['12.x-dev', '12.1.x-dev', '12.1.0-rc1', '12.1.0-beta1', '12.0.x-dev', '12.0.2', '12.0.1', '12.0.0', '12.0.0-rc1', '12.0.0-beta1'];

    yield 'major' => ['11', $current, '11.4.8'];
    yield 'older major' => ['10', $current, '10.6.18'];
    yield 'patch version selects the newest patch of its minor' => ['11.1.0', $current, '11.1.10'];
    yield 'minor version selects the newest release of its major' => ['11.1', $current, '11.4.8'];
    yield 'major ignores the next minor pre-release' => ['11', ['11.5.0-beta1', '11.4.8'], '11.4.8'];
    yield 'beta flag selects the next minor pre-release' => ['11@beta', ['11.5.0-beta1', '11.4.8'], '11.5.0-beta1'];
    yield 'beta flag without a newer pre-release selects the stable release' => ['11@beta', $current, '11.4.8'];
    yield 'major without a stable release selects the newest pre-release' => ['12', $current, '12.0.0-beta1'];
    yield 'patch version without a stable release selects the newest pre-release' => ['12.0.0', $current, '12.0.0-beta1'];
    yield 'beta flag without a stable release' => ['12@beta', $current, '12.0.0-beta1'];
    yield 'alpha flag selects the newest pre-release' => ['12@alpha', $current, '12.0.0-beta1'];
    yield 'flag above every release selects the newest pre-release' => ['12@rc', $current, '12.0.0-beta1'];
    yield 'major after its first stable release' => ['12', $later, '12.0.2'];
    yield 'patch version after its first stable release' => ['12.0.0', $later, '12.0.2'];
    yield 'beta flag after the first stable release' => ['12@beta', $later, '12.1.0-rc1'];
    yield 'upper-case release candidate flag' => ['12@RC', $later, '12.1.0-rc1'];
    yield 'stable flag' => ['12@stable', $later, '12.0.2'];
    yield 'minor with only a development branch' => ['11.5.0', $current, NULL];
    yield 'major with no versions' => ['13', $current, NULL];
    yield 'no versions' => ['11', [], NULL];
    yield 'unsupported constraint' => ['^11', $current, NULL];
    yield 'dev flag' => ['11@dev', $current, NULL];
    yield 'unsupported stability flag' => ['11@nightly', $current, NULL];
  }

}
