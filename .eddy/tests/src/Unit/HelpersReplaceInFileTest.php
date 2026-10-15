<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\replace_in_file;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\replace_in_file')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersReplaceInFileTest extends UnitTestCase {

  #[DataProvider('dataProviderReplaceInFile')]
  public function testReplaceInFile(string $initial_content, string $pattern, string $replacement, string $expected_content): void {
    $file = self::$tmp . '/replace_' . uniqid() . '.txt';
    File::dump($file, $initial_content);
    $result = replace_in_file($file, $pattern, $replacement);
    $this->assertSame($expected_content, $result);
    $this->assertSame($expected_content, File::read($file));
  }

  public static function dataProviderReplaceInFile(): \Iterator {
    yield 'simple replacement' => [
      'initial_content' => 'Hello World',
      'pattern' => '/World/',
      'replacement' => 'PHP',
      'expected_content' => 'Hello PHP',
    ];
    yield 'regex replacement' => [
      'initial_content' => 'version: 1.0.0',
      'pattern' => '/version: \d+\.\d+\.\d+/',
      'replacement' => 'version: 2.0.0',
      'expected_content' => 'version: 2.0.0',
    ];
    yield 'no match leaves content unchanged' => [
      'initial_content' => 'Hello World',
      'pattern' => '/Goodbye/',
      'replacement' => 'Hi',
      'expected_content' => 'Hello World',
    ];
    yield 'multiple matches' => [
      'initial_content' => 'foo bar foo baz foo',
      'pattern' => '/foo/',
      'replacement' => 'qux',
      'expected_content' => 'qux bar qux baz qux',
    ];
  }

  public function testReplaceInFileMissingFile(): void {
    $file = self::$tmp . '/nonexistent_' . uniqid() . '.txt';
    $this->mockQuit(1);
    set_error_handler(static fn(): bool => TRUE);

    try {
      $output = $this->capture(fn(): string => replace_in_file($file, '/foo/', 'bar'), 1);
    }
    finally {
      restore_error_handler();
    }

    $this->assertStringContainsString('Unable to read file', $output);
  }

  public function testReplaceInFileInvalidRegex(): void {
    $file = self::$tmp . '/invalid_regex_' . uniqid() . '.txt';
    File::dump($file, 'test content');
    $this->mockQuit(1);
    set_error_handler(static fn(): bool => TRUE);

    try {
      $output = $this->capture(fn(): string => replace_in_file($file, '/(?invalid/', 'bar'), 1);
    }
    finally {
      restore_error_handler();
    }

    $this->assertStringContainsString('Regex replacement failed', $output);
  }

}
