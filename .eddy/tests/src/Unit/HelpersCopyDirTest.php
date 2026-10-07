<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\copy_dir;
use DrevOps\Eddy\Tests\Traits\DirectoryFixtureTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for copy_dir() function.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 */
#[CoversFunction('DrevOps\Eddy\DevTools\copy_dir')]
#[Group('p0')]
final class HelpersCopyDirTest extends UnitTestCase {

  use DirectoryFixtureTrait;

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderCopyDir')]
  public function testCopyDir(array $structure, array $expected_files): void {
    $src = self::$tmp . '/src_' . uniqid();
    $dst = self::$tmp . '/dst_' . uniqid();

    $this->createDirectoryStructure($src, $structure);

    copy_dir($src, $dst);

    $this->assertDirectoryExists($dst);

    foreach ($expected_files as $relative_path => $expected_content) {
      $full_path = $dst . '/' . $relative_path;
      if ($expected_content === NULL) {
        $this->assertDirectoryExists($full_path, sprintf('Directory %s should exist', $relative_path));
      }
      else {
        $this->assertFileExists($full_path, sprintf('File %s should exist', $relative_path));
        $this->assertSame($expected_content, file_get_contents($full_path), sprintf('File %s should have correct content', $relative_path));
      }
    }
  }

  public static function dataProviderCopyDir(): \Iterator {
    yield 'single file' => [
      'structure' => [
        'file.txt' => 'content',
      ],
      'expected_files' => [
        'file.txt' => 'content',
      ],
    ];
    yield 'multiple files' => [
      'structure' => [
        'file1.txt' => 'content1',
        'file2.txt' => 'content2',
        'file3.txt' => 'content3',
      ],
      'expected_files' => [
        'file1.txt' => 'content1',
        'file2.txt' => 'content2',
        'file3.txt' => 'content3',
      ],
    ];
    yield 'nested directories' => [
      'structure' => [
        'subdir' => [
          'nested.txt' => 'nested content',
        ],
      ],
      'expected_files' => [
        'subdir' => NULL,
        'subdir/nested.txt' => 'nested content',
      ],
    ];
    yield 'deeply nested directories' => [
      'structure' => [
        'level1' => [
          'level2' => [
            'level3' => [
              'deep.txt' => 'deep content',
            ],
          ],
        ],
      ],
      'expected_files' => [
        'level1' => NULL,
        'level1/level2' => NULL,
        'level1/level2/level3' => NULL,
        'level1/level2/level3/deep.txt' => 'deep content',
      ],
    ];
    yield 'mixed files and directories' => [
      'structure' => [
        'root.txt' => 'root content',
        'subdir1' => [
          'file1.txt' => 'sub1 content',
        ],
        'subdir2' => [
          'file2.txt' => 'sub2 content',
          'nested' => [
            'file3.txt' => 'nested content',
          ],
        ],
      ],
      'expected_files' => [
        'root.txt' => 'root content',
        'subdir1' => NULL,
        'subdir1/file1.txt' => 'sub1 content',
        'subdir2' => NULL,
        'subdir2/file2.txt' => 'sub2 content',
        'subdir2/nested' => NULL,
        'subdir2/nested/file3.txt' => 'nested content',
      ],
    ];
    yield 'empty directory' => [
      'structure' => [],
      'expected_files' => [],
    ];
    yield 'directory with empty subdirectory' => [
      'structure' => [
        'file.txt' => 'content',
        'empty_dir' => [],
      ],
      'expected_files' => [
        'file.txt' => 'content',
        'empty_dir' => NULL,
      ],
    ];
  }

  public function testCopyDirDestinationAlreadyExists(): void {
    $src = self::$tmp . '/src_' . uniqid();
    $dst = self::$tmp . '/dst_' . uniqid();

    $this->createDirectoryStructure($src, [
      'new_file.txt' => 'new content',
    ]);

    mkdir($dst, 0755, TRUE);
    file_put_contents($dst . '/existing.txt', 'existing content');

    copy_dir($src, $dst);

    $this->assertFileExists($dst . '/new_file.txt');
    $this->assertSame('new content', file_get_contents($dst . '/new_file.txt'));
    $this->assertFileExists($dst . '/existing.txt');
    $this->assertSame('existing content', file_get_contents($dst . '/existing.txt'));
  }

  public function testCopyDirOverwritesExistingFile(): void {
    $src = self::$tmp . '/src_' . uniqid();
    $dst = self::$tmp . '/dst_' . uniqid();

    $this->createDirectoryStructure($src, [
      'file.txt' => 'new content',
    ]);

    mkdir($dst, 0755, TRUE);
    file_put_contents($dst . '/file.txt', 'old content');

    copy_dir($src, $dst);

    $this->assertSame('new content', file_get_contents($dst . '/file.txt'));
  }

}
