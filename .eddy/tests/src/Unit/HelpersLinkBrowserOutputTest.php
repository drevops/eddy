<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\link_browser_output;
use AlexSkrypnyk\File\File;
use Laravel\SerializableClosure\SerializableClosure;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\link_browser_output')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersLinkBrowserOutputTest extends UnitTestCase {

  public function testMissingWebrootDoesNothing(): void {
    $webroot = self::$tmp . '/web_' . uniqid();
    $logs = self::$tmp . '/logs_' . uniqid();

    link_browser_output($webroot, $logs);

    $this->assertDirectoryDoesNotExist($logs . '/browser_output');
    $this->assertDirectoryDoesNotExist($webroot);
  }

  public function testCreatesSymlinkFromScratch(): void {
    [$webroot, $logs] = self::createWebroot();

    link_browser_output($webroot, $logs);

    $target = $logs . '/browser_output';
    $link = $webroot . '/sites/simpletest/browser_output';

    $this->assertDirectoryExists($target);
    $this->assertTrue(is_link($link));
    $this->assertSame($target, readlink($link));

    File::dump($link . '/page.html', 'dump');
    $this->assertFileExists($target . '/page.html');
  }

  public function testReusesExistingTargetAndSimpletestDir(): void {
    [$webroot, $logs] = self::createWebroot();
    File::dump($logs . '/browser_output/existing.html', 'keep');
    File::mkdir($webroot . '/sites/simpletest', 0755);

    link_browser_output($webroot, $logs);

    $link = $webroot . '/sites/simpletest/browser_output';
    $this->assertTrue(is_link($link));
    $this->assertFileExists($logs . '/browser_output/existing.html');
  }

  #[DataProvider('dataProviderReplacesExisting')]
  public function testReplacesExisting(SerializableClosure $setup): void {
    [$webroot, $logs] = self::createWebroot();
    $link = $webroot . '/sites/simpletest/browser_output';
    self::cu($setup)($link);

    link_browser_output($webroot, $logs);

    $this->assertTrue(is_link($link));
    $this->assertSame($logs . '/browser_output', readlink($link));
  }

  public static function dataProviderReplacesExisting(): \Iterator {
    yield 'directory' => [self::cw(static fn(string $link) => File::dump($link . '/stale.html', 'stale'))];

    yield 'symlink' => [
      self::cw(static function (string $link): void {
        File::mkdir(dirname($link), 0755);
        $other = self::$tmp . '/other_' . uniqid();
        File::mkdir($other, 0755);
        symlink($other, $link);
      }),
    ];

    yield 'file' => [
      self::cw(static function (string $link): void {
        File::mkdir(dirname($link), 0755);
        File::dump($link, 'i am a file');
      }),
    ];
  }

  #[DataProvider('dataProviderFailsWhenPathCannotBeCreated')]
  public function testFailsWhenPathCannotBeCreated(string $function, string $expected_message): void {
    [$webroot, $logs] = self::createWebroot();
    $this->mockFunction($function, fn(): false => FALSE);
    $this->mockQuit(1);

    $output = $this->capture(fn() => link_browser_output($webroot, $logs), 1);

    $this->assertStringContainsString(sprintf($expected_message, $webroot, $logs), $output);
  }

  public static function dataProviderFailsWhenPathCannotBeCreated(): \Iterator {
    yield 'logs directory' => ['mkdir', 'Unable to create directory %2$s/browser_output.'];
    yield 'symlink' => ['symlink', 'Unable to create symlink %1$s/sites/simpletest/browser_output to %2$s/browser_output.'];
  }

  /**
   * @return array{0: string, 1: string}
   *   Tuple of [webroot, logs_dir] absolute paths.
   */
  protected static function createWebroot(): array {
    $base = self::$tmp . '/proj_' . uniqid();
    $webroot = $base . '/build/web';
    File::mkdir($webroot, 0755);

    return [$webroot, $base . '/.logs'];
  }

}
