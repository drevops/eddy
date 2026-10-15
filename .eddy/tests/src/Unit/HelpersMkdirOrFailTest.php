<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\mkdir_or_fail;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\mkdir_or_fail')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersMkdirOrFailTest extends UnitTestCase {

  #[DataProvider('dataProviderCreatesMissingDirectory')]
  public function testCreatesMissingDirectory(?int $mode, int $expected_mode): void {
    $parent = self::$tmp . '/parent';
    $dir = $parent . '/child';
    $umask = umask(0022);

    try {
      if ($mode === NULL) {
        mkdir_or_fail($dir);
      }
      else {
        mkdir_or_fail($dir, $mode);
      }
    }
    finally {
      umask($umask);
    }

    $this->assertDirectoryExists($dir);
    $this->assertSame($expected_mode, fileperms($dir) & 0777);
    $this->assertSame($expected_mode, fileperms($parent) & 0777, 'Every directory created takes the mode.');
  }

  public static function dataProviderCreatesMissingDirectory(): \Iterator {
    yield 'default mode' => [NULL, 0755];
    yield 'owner only' => [0700, 0700];
  }

  public function testKeepsModeOfExistingDirectory(): void {
    $dir = self::$tmp . '/existing_mode';
    File::mkdir($dir, 0755);
    chmod($dir, 0755);

    mkdir_or_fail($dir, 0700);

    $this->assertSame(0755, fileperms($dir) & 0777);
  }

  public function testKeepsExistingDirectory(): void {
    $dir = self::$tmp . '/existing';
    File::dump($dir . '/file.txt', 'content');

    mkdir_or_fail($dir);

    $this->assertFileExists($dir . '/file.txt');
  }

  public function testFailsWhenPathIsFile(): void {
    $path = self::$tmp . '/file';
    File::dump($path, 'content');
    $this->mockQuit(1);

    $output = $this->capture(fn() => mkdir_or_fail($path), 1);

    $this->assertStringContainsString('Unable to create directory ' . $path . '.', $output);
  }

}
