<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\replace_in_file;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\DevTools\replace_in_file')]
#[Group('p0')]
final class HelpersReplaceInFileTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderReplaceInFile')]
  public function testReplaceInFile(string $initial_content, string $pattern, string $replacement, string $expected_content): void {
    $file = self::$tmp . '/replace_' . uniqid() . '.txt';
    file_put_contents($file, $initial_content);
    $result = replace_in_file($file, $pattern, $replacement);
    $this->assertSame($expected_content, $result);
    $this->assertSame($expected_content, file_get_contents($file));
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
    ob_start();
    try {
      replace_in_file($file, '/foo/', 'bar');
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      restore_error_handler();
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Unable to read file', $output);
    }
  }

  public function testReplaceInFileInvalidRegex(): void {
    $file = self::$tmp . '/invalid_regex_' . uniqid() . '.txt';
    file_put_contents($file, 'test content');
    $this->mockQuit(1);
    set_error_handler(static fn(): bool => TRUE);
    ob_start();
    try {
      replace_in_file($file, '/(?invalid/', 'bar');
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      restore_error_handler();
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Regex replacement failed', $output);
    }
  }

}
