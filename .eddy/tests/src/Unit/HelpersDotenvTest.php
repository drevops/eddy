<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\dotenv_read;
use function DrevOps\Eddy\DevTools\dotenv_write_var;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for dotenv_read() and dotenv_write_var() helpers.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\dotenv_read')]
#[CoversFunction('DrevOps\Eddy\DevTools\dotenv_write_var')]
#[Group('p0')]
final class HelpersDotenvTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderDotenvRead')]
  public function testDotenvRead(?string $contents, array $expected): void {
    $file = self::$tmp . '/dotenv_read_' . uniqid();
    if ($contents !== NULL) {
      file_put_contents($file, $contents);
    }

    $result = dotenv_read($file);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderDotenvRead(): \Iterator {
    yield 'file does not exist' => [
      'contents' => NULL,
      'expected' => [],
    ];
    yield 'empty file' => [
      'contents' => '',
      'expected' => [],
    ];
    yield 'single variable' => [
      'contents' => "FOO=bar\n",
      'expected' => ['FOO' => 'bar'],
    ];
    yield 'multiple variables' => [
      'contents' => "FOO=bar\nBAZ=qux\nWEBSERVER_PORT=8123\n",
      'expected' => ['FOO' => 'bar', 'BAZ' => 'qux', 'WEBSERVER_PORT' => '8123'],
    ];
    yield 'comments and blank lines are ignored' => [
      'contents' => "# This is a comment\n\nFOO=bar\n  # indented comment\nBAZ=qux\n\n",
      'expected' => ['FOO' => 'bar', 'BAZ' => 'qux'],
    ];
    yield 'whitespace around keys and values is trimmed' => [
      'contents' => "  FOO  =  bar  \n",
      'expected' => ['FOO' => 'bar'],
    ];
    yield 'value can be empty' => [
      'contents' => "FOO=\n",
      'expected' => ['FOO' => ''],
    ];
    yield 'value with equals sign preserved' => [
      'contents' => "URL=http://example.com/?a=1&b=2\n",
      'expected' => ['URL' => 'http://example.com/?a=1&b=2'],
    ];
    yield 'double quoted value is unquoted' => [
      'contents' => "FOO=\"bar baz\"\n",
      'expected' => ['FOO' => 'bar baz'],
    ];
    yield 'single quoted value is unquoted' => [
      'contents' => "FOO='bar baz'\n",
      'expected' => ['FOO' => 'bar baz'],
    ];
    yield 'mismatched quotes left intact' => [
      'contents' => "FOO=\"bar'\n",
      'expected' => ['FOO' => '"bar\''],
    ];
    yield 'lines without equals are ignored' => [
      'contents' => "FOO=bar\ninvalid_line\nBAZ=qux\n",
      'expected' => ['FOO' => 'bar', 'BAZ' => 'qux'],
    ];
    yield 'empty key is ignored' => [
      'contents' => "=value\nFOO=bar\n",
      'expected' => ['FOO' => 'bar'],
    ];
    yield 'CRLF line endings' => [
      'contents' => "FOO=bar\r\nBAZ=qux\r\n",
      'expected' => ['FOO' => 'bar', 'BAZ' => 'qux'],
    ];
    yield 'CR line endings' => [
      'contents' => "FOO=bar\rBAZ=qux\r",
      'expected' => ['FOO' => 'bar', 'BAZ' => 'qux'],
    ];
    yield 'last assignment wins for duplicate keys' => [
      'contents' => "FOO=first\nFOO=second\n",
      'expected' => ['FOO' => 'second'],
    ];
  }

  public function testDotenvWriteVarCreatesFileWhenMissing(): void {
    $file = self::$tmp . '/dotenv_write_' . uniqid();
    $this->assertFileDoesNotExist($file);

    dotenv_write_var('FOO', 'bar', $file);

    $this->assertFileExists($file);
    $this->assertSame("FOO=bar\n", file_get_contents($file));
  }

  public function testDotenvWriteVarAppendsToExistingFile(): void {
    $file = self::$tmp . '/dotenv_write_' . uniqid();
    file_put_contents($file, "EXISTING=value\n");

    dotenv_write_var('NEW', 'data', $file);

    $this->assertSame("EXISTING=value\nNEW=data\n", file_get_contents($file));
  }

  public function testDotenvWriteVarReplacesExistingKeyInPlace(): void {
    $file = self::$tmp . '/dotenv_write_' . uniqid();
    file_put_contents($file, "FOO=old\nBAR=keep\n");

    dotenv_write_var('FOO', 'new', $file);

    $this->assertSame("FOO=new\nBAR=keep\n", file_get_contents($file));
  }

  public function testDotenvWriteVarPreservesCommentsAndBlankLines(): void {
    $file = self::$tmp . '/dotenv_write_' . uniqid();
    $original = "# Top comment\n\nFOO=old\n\n# Another comment\nBAR=keep\n";
    file_put_contents($file, $original);

    dotenv_write_var('FOO', 'new', $file);

    $this->assertSame("# Top comment\n\nFOO=new\n\n# Another comment\nBAR=keep\n", file_get_contents($file));
  }

  public function testDotenvWriteVarReplacesLastMatchToAgreeWithRead(): void {
    $file = self::$tmp . '/dotenv_write_' . uniqid();
    file_put_contents($file, "FOO=first\nBAR=keep\nFOO=second\n");

    dotenv_write_var('FOO', 'new', $file);

    $this->assertSame("FOO=first\nBAR=keep\nFOO=new\n", file_get_contents($file));
  }

  public function testDotenvWriteVarHandlesFileWithoutTrailingNewline(): void {
    $file = self::$tmp . '/dotenv_write_' . uniqid();
    file_put_contents($file, 'EXISTING=value');

    dotenv_write_var('NEW', 'data', $file);

    $this->assertSame("EXISTING=value\nNEW=data\n", file_get_contents($file));
  }

  public function testDotenvWriteVarAndReadRoundTrip(): void {
    $file = self::$tmp . '/dotenv_roundtrip_' . uniqid();

    dotenv_write_var('WEBSERVER_PORT', '8123', $file);
    dotenv_write_var('OTHER_VAR', 'hello', $file);
    dotenv_write_var('WEBSERVER_PORT', '8124', $file);

    $vars = dotenv_read($file);
    $this->assertSame(['WEBSERVER_PORT' => '8124', 'OTHER_VAR' => 'hello'], $vars);
  }

}
