<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\selenium_container;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the selenium_container() helper.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\selenium_container')]
#[Group('p0')]
final class HelpersSeleniumContainerTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderSeleniumContainer')]
  public function testSeleniumContainer(string $port, string $expected): void {
    $this->assertSame($expected, selenium_container($port));
  }

  public static function dataProviderSeleniumContainer(): \Iterator {
    yield 'default port' => [
      'port' => '4444',
      'expected' => 'selenium-4444',
    ];
    yield 'discovered port' => [
      'port' => '4445',
      'expected' => 'selenium-4445',
    ];
  }

}
