<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\chmod_recursive;
use function DrevOps\Eddy\DevTools\remove_dir;
use DrevOps\Eddy\Tests\Traits\DirectoryFixtureTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\DevTools\remove_dir')]
#[CoversFunction('DrevOps\Eddy\DevTools\chmod_recursive')]
#[Group('p0')]
final class HelpersFilesystemTest extends UnitTestCase {

  use DirectoryFixtureTrait;

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testRemoveDirMissing(): void {
    remove_dir(self::$tmp . '/nonexistent_' . uniqid());
    $this->expectNotToPerformAssertions();
  }

  #[DataProvider('dataProviderRemoveDir')]
  public function testRemoveDir(array $structure, bool $has_symlink): void {
    $dir = self::$tmp . '/remove_' . uniqid();
    $this->createDirectoryStructure($dir, $structure);
    if ($has_symlink) {
      file_put_contents($dir . '/target.txt', 'target content');
      symlink($dir . '/target.txt', $dir . '/subdir/link.txt');
    }
    $this->assertDirectoryExists($dir);
    remove_dir($dir);
    $this->assertDirectoryDoesNotExist($dir);
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
    $this->createDirectoryStructure($dir, $structure);
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
    mkdir($dir, 0755, TRUE);
    mkdir($target_dir, 0755, TRUE);
    file_put_contents($target_dir . '/target.txt', 'content');
    chmod($target_dir . '/target.txt', 0644);
    symlink($target_dir . '/target.txt', $dir . '/link.txt');
    chmod_recursive($dir, 0755);
    $this->assertTrue(is_link($dir . '/link.txt'));
    $this->assertSame(0644, fileperms($target_dir . '/target.txt') & 0777);
  }

}
