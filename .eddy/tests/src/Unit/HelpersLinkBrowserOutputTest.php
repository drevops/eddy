<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\link_browser_output;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\Tooling\link_browser_output')]
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
    [$webroot, $logs] = $this->createWebroot();

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
    [$webroot, $logs] = $this->createWebroot();
    File::dump($logs . '/browser_output/existing.html', 'keep');
    File::mkdir($webroot . '/sites/simpletest', 0755);

    link_browser_output($webroot, $logs);

    $link = $webroot . '/sites/simpletest/browser_output';
    $this->assertTrue(is_link($link));
    $this->assertFileExists($logs . '/browser_output/existing.html');
  }

  public function testReplacesExistingDirectory(): void {
    [$webroot, $logs] = $this->createWebroot();
    $link = $webroot . '/sites/simpletest/browser_output';
    File::dump($link . '/stale.html', 'stale');

    link_browser_output($webroot, $logs);

    $this->assertTrue(is_link($link));
    $this->assertSame($logs . '/browser_output', readlink($link));
  }

  public function testReplacesExistingSymlink(): void {
    [$webroot, $logs] = $this->createWebroot();
    File::mkdir($webroot . '/sites/simpletest', 0755);
    $link = $webroot . '/sites/simpletest/browser_output';
    $other = self::$tmp . '/other_' . uniqid();
    File::mkdir($other, 0755);
    symlink($other, $link);

    link_browser_output($webroot, $logs);

    $this->assertTrue(is_link($link));
    $this->assertSame($logs . '/browser_output', readlink($link));
  }

  public function testReplacesExistingFile(): void {
    [$webroot, $logs] = $this->createWebroot();
    File::mkdir($webroot . '/sites/simpletest', 0755);
    $link = $webroot . '/sites/simpletest/browser_output';
    File::dump($link, 'i am a file');

    link_browser_output($webroot, $logs);

    $this->assertTrue(is_link($link));
    $this->assertSame($logs . '/browser_output', readlink($link));
  }

  /**
   * Create a throwaway project layout with an assembled web root.
   *
   * @return array{0: string, 1: string}
   *   Tuple of [webroot, logs_dir] absolute paths.
   */
  protected function createWebroot(): array {
    $base = self::$tmp . '/proj_' . uniqid();
    $webroot = $base . '/build/web';
    File::mkdir($webroot, 0755);

    return [$webroot, $base . '/.logs'];
  }

}
