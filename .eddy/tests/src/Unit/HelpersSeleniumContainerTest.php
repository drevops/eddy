<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\selenium_container;
use function DrevOps\Eddy\Tooling\selenium_container_project;
use function DrevOps\Eddy\Tooling\selenium_container_remove;
use function DrevOps\Eddy\Tooling\selenium_container_running;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the helpers that name, inspect and remove the Selenium container.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\Tooling\selenium_container')]
#[CoversFunction('DrevOps\Eddy\Tooling\selenium_container_project')]
#[CoversFunction('DrevOps\Eddy\Tooling\selenium_container_remove')]
#[CoversFunction('DrevOps\Eddy\Tooling\selenium_container_running')]
#[RunTestsInSeparateProcesses]
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

  #[DataProvider('dataProviderSeleniumContainerProject')]
  public function testSeleniumContainerProject(string $inspect_output, ?string $expected): void {
    $this->mockShellExecOutputs([self::seleniumInspectCommand('4444') => $inspect_output]);

    $this->assertSame($expected, selenium_container_project('4444'));
  }

  public static function dataProviderSeleniumContainerProject(): \Iterator {
    yield 'no container' => ['', NULL];
    yield 'container with the project label' => ["/selenium-4444|/www/project\n", '/www/project'];
    yield 'project root with spaces' => ["/selenium-4444|/www/my project\n", '/www/my project'];
    yield 'container without the label' => ["/selenium-4444|\n", ''];
    yield 'container without labels' => ["/selenium-4444|<no value>\n", ''];
  }

  #[DataProvider('dataProviderSeleniumContainerRunning')]
  public function testSeleniumContainerRunning(string $inspect_output, bool $expected): void {
    $this->mockShellExecOutputs([self::seleniumRunningCommand('4444') => $inspect_output]);

    $this->assertSame($expected, selenium_container_running('4444'));
  }

  public static function dataProviderSeleniumContainerRunning(): \Iterator {
    yield 'running container' => ["true\n", TRUE];
    yield 'stopped container' => ["false\n", FALSE];
    yield 'no container' => ['', FALSE];
  }

  #[DataProvider('dataProviderSeleniumContainerRemove')]
  public function testSeleniumContainerRemove(string $port, string $expected): void {
    $this->mockPassthru(['cmd' => $expected]);

    selenium_container_remove($port);

    $this->mockPassthruAssertAllMocksConsumed();
  }

  public static function dataProviderSeleniumContainerRemove(): \Iterator {
    yield 'default port' => ['4444', "docker rm -f 'selenium-4444' >/dev/null 2>&1"];
    yield 'discovered port' => ['4445', "docker rm -f 'selenium-4445' >/dev/null 2>&1"];
  }

}
