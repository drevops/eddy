<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\chmod_recursive;
use function DrevOps\Eddy\Tooling\is_link_path;
use function DrevOps\Eddy\Tooling\remove_dir;
use function DrevOps\Eddy\Tooling\remove_link;
use AlexSkrypnyk\File\File;
use DrevOps\Eddy\Tests\Traits\DirectoryFixtureTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\remove_dir')]
#[CoversFunction('DrevOps\Eddy\Tooling\chmod_recursive')]
#[CoversFunction('DrevOps\Eddy\Tooling\is_link_path')]
#[CoversFunction('DrevOps\Eddy\Tooling\remove_link')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersFilesystemTest extends UnitTestCase {

  use DirectoryFixtureTrait;

  public function testRemoveDirMissing(): void {
    $this->assertTrue(remove_dir(self::$tmp . '/nonexistent_' . uniqid()));
  }

  #[DataProvider('dataProviderRemoveDir')]
  public function testRemoveDir(array $structure, bool $has_symlink): void {
    $dir = self::$tmp . '/remove_' . uniqid();
    self::directoryFixtureCreate($dir, $structure);
    if ($has_symlink) {
      File::dump($dir . '/target.txt', 'target content');
      symlink($dir . '/target.txt', $dir . '/subdir/link.txt');
    }
    $this->assertDirectoryExists($dir);
    $this->assertTrue(remove_dir($dir));
    $this->assertDirectoryDoesNotExist($dir);
  }

  public function testRemoveDirKeepsLinkedDirectory(): void {
    $target = self::$tmp . '/linked_' . uniqid();
    File::dump($target . '/kept.txt', 'kept');
    $dir = self::$tmp . '/remove_' . uniqid();
    File::mkdir($dir);
    symlink($target, $dir . '/link');

    $this->assertTrue(remove_dir($dir));
    $this->assertDirectoryDoesNotExist($dir);
    $this->assertFileExists($target . '/kept.txt', 'The files of a linked directory stay.');
  }

  /**
   * @param string $type
   *   What the path is: 'file', 'directory', 'symlink', 'junction' or
   *   'missing'.
   */
  #[DataProvider('dataProviderIsLinkPath')]
  public function testIsLinkPath(string $type, bool $windows, bool $expected): void {
    $this->mockWindows($windows);
    $path = self::$tmp . '/path_' . uniqid();

    match ($type) {
      'file' => File::dump($path, 'content'),
      'directory', 'junction' => File::mkdir($path),
      'symlink' => symlink(self::$tmp, $path),
      default => NULL,
    };

    // A junction is a directory that readlink() resolves on Windows.
    if ($type === 'junction') {
      $this->mockFunction('readlink', fn(string $link): string|false => $link === $path ? self::$tmp : \readlink($link));
    }

    $this->assertSame($expected, is_link_path($path));
  }

  public static function dataProviderIsLinkPath(): \Iterator {
    yield 'file' => ['file', FALSE, FALSE];
    yield 'directory' => ['directory', FALSE, FALSE];
    yield 'symlink' => ['symlink', FALSE, TRUE];
    yield 'missing' => ['missing', FALSE, FALSE];
    yield 'directory on Windows' => ['directory', TRUE, FALSE];
    yield 'junction on Windows' => ['junction', TRUE, TRUE];
    yield 'symlink on Windows' => ['symlink', TRUE, TRUE];
  }

  #[DataProvider('dataProviderRemoveLink')]
  public function testRemoveLink(bool $windows, string $expected_call): void {
    $this->mockWindows($windows);
    $link = self::$tmp . '/link_' . uniqid();
    symlink(self::$tmp, $link);
    $calls = [];
    $this->mockFunction('rmdir', function (string $path) use (&$calls): bool {
      $calls[] = 'rmdir';

      return self::removeLinkOnHost($path);
    });
    $this->mockFunction('unlink', function (string $path) use (&$calls): bool {
      $calls[] = 'unlink';

      return self::removeLinkOnHost($path);
    });

    $this->assertTrue(remove_link($link));
    $this->assertSame([$expected_call], $calls);
    $this->assertDirectoryExists(self::$tmp, 'The target of the link stays.');
  }

  public static function dataProviderRemoveLink(): \Iterator {
    yield 'posix' => [FALSE, 'unlink'];
    yield 'windows, a link to a directory' => [TRUE, 'rmdir'];
  }

  /**
   * Remove a link with the call that the host needs.
   *
   * A link to a directory is removed with unlink() on POSIX systems and with
   * rmdir() on Windows.
   */
  protected static function removeLinkOnHost(string $path): bool {
    return PHP_OS_FAMILY === 'Windows' ? \rmdir($path) : \unlink($path);
  }

  public function testRemoveDirFailure(): void {
    $dir = self::$tmp . '/remove_' . uniqid();
    self::directoryFixtureCreate($dir, ['file.txt' => 'content']);
    $this->mockFunction('rmdir', fn(): false => FALSE);

    $this->assertFalse(remove_dir($dir));
    $this->assertDirectoryExists($dir);
  }

  public static function dataProviderRemoveDir(): \Iterator {
    yield 'empty directory' => [
      'structure' => [],
      'has_symlink' => FALSE,
    ];
    yield 'with files and nested dirs' => [
      'structure' => [
        'file1.txt' => 'content1',
        'subdir' => [
          'file2.txt' => 'content2',
          'nested' => [
            'file3.txt' => 'content3',
          ],
        ],
      ],
      'has_symlink' => FALSE,
    ];
    yield 'with symlinks' => [
      'structure' => [
        'subdir' => [],
      ],
      'has_symlink' => TRUE,
    ];
  }

  public function testChmodRecursiveMissing(): void {
    chmod_recursive(self::$tmp . '/nonexistent_' . uniqid(), 0755);
    $this->expectNotToPerformAssertions();
  }

  #[DataProvider('dataProviderChmodRecursive')]
  public function testChmodRecursive(array $structure, int $mode, array $expected_perms): void {
    $dir = self::$tmp . '/chmod_' . uniqid();
    self::directoryFixtureCreate($dir, $structure);
    chmod_recursive($dir, $mode);
    foreach ($expected_perms as $relative_path => $expected_mode) {
      $path = $relative_path === '.' ? $dir : $dir . '/' . $relative_path;
      $this->assertSame($expected_mode, fileperms($path) & 0777, sprintf('Permissions mismatch for %s', $relative_path));
    }
  }

  public static function dataProviderChmodRecursive(): \Iterator {
    yield 'flat files' => [
      'structure' => [
        'file1.txt' => 'content1',
        'file2.txt' => 'content2',
      ],
      'mode' => 0755,
      'expected_perms' => [
        '.' => 0755,
        'file1.txt' => 0755,
        'file2.txt' => 0755,
      ],
    ];
    yield 'nested directories' => [
      'structure' => [
        'subdir' => [
          'file.txt' => 'content',
        ],
      ],
      'mode' => 0700,
      'expected_perms' => [
        '.' => 0700,
        'subdir' => 0700,
        'subdir/file.txt' => 0700,
      ],
    ];
  }

  public function testChmodRecursiveSkipsSymlinks(): void {
    $dir = self::$tmp . '/chmod_symlink_' . uniqid();
    $target_dir = self::$tmp . '/chmod_target_' . uniqid();
    File::mkdir($dir, 0755);
    File::dump($target_dir . '/target.txt', 'content');
    chmod($target_dir . '/target.txt', 0644);
    symlink($target_dir . '/target.txt', $dir . '/link.txt');
    chmod_recursive($dir, 0755);
    $this->assertTrue(is_link($dir . '/link.txt'));
    $this->assertSame(0644, fileperms($target_dir . '/target.txt') & 0777);
  }

}
