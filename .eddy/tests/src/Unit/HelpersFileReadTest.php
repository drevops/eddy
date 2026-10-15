<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\file_read;
use function DrevOps\Eddy\Tooling\file_read_or_fail;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\file_read')]
#[CoversFunction('DrevOps\Eddy\Tooling\file_read_or_fail')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersFileReadTest extends UnitTestCase {

  protected const string CONTENT = "line 1\nline 2\n";

  #[DataProvider('dataProviderFileRead')]
  public function testFileRead(string $type, string $expected): void {
    $path = self::createPath($type);
    $errors = [];
    set_error_handler(static function (int $level, string $message) use (&$errors): bool {
      $errors[] = $message;

      return TRUE;
    });

    try {
      $content = file_read($path);
    }
    finally {
      restore_error_handler();
    }

    $this->assertSame($expected, $content);
    $this->assertSame([], $errors, 'A missing file and a directory read as empty without a PHP error.');
  }

  public static function dataProviderFileRead(): \Iterator {
    yield 'file' => ['file', self::CONTENT];
    yield 'empty file' => ['empty', ''];
    yield 'missing file' => ['missing', ''];
    yield 'directory' => ['directory', ''];
  }

  public function testFileReadUnreadable(): void {
    $path = self::createPath('file');
    $this->mockFunction('file_get_contents', fn(): false => FALSE);

    $this->assertSame('', file_read($path));
  }

  #[DataProvider('dataProviderFileReadOrFail')]
  public function testFileReadOrFail(string $type, string $expected): void {
    $this->assertSame($expected, file_read_or_fail(self::createPath($type)));
  }

  public static function dataProviderFileReadOrFail(): \Iterator {
    yield 'file' => ['file', self::CONTENT];
    yield 'empty file' => ['empty', ''];
  }

  #[DataProvider('dataProviderFileReadOrFailFails')]
  public function testFileReadOrFailFails(string $type, bool $unreadable): void {
    $path = self::createPath($type);

    if ($unreadable) {
      $this->mockFunction('file_get_contents', fn(): false => FALSE);
    }

    $this->mockQuit(1);

    $output = $this->capture(fn(): string => file_read_or_fail($path), 1);

    $this->assertStringContainsString('Unable to read ' . $path . '.', $output);
  }

  public static function dataProviderFileReadOrFailFails(): \Iterator {
    yield 'missing file' => ['missing', FALSE];
    yield 'directory' => ['directory', FALSE];
    yield 'unreadable file' => ['file', TRUE];
  }

  /**
   * Create a path of a type under the temporary directory.
   *
   * @param string $type
   *   'file' for a file that holds CONTENT, 'empty' for an empty file,
   *   'directory' for a directory, or 'missing' for a path that does not
   *   exist.
   *
   * @return string
   *   The path.
   */
  protected static function createPath(string $type): string {
    $path = self::$tmp . '/read_' . uniqid();

    if ($type === 'file') {
      File::dump($path, self::CONTENT);
    }
    elseif ($type === 'empty') {
      File::dump($path, '');
    }
    elseif ($type === 'directory') {
      File::mkdir($path, 0755);
    }

    return $path;
  }

}
