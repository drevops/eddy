<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\dotenv_read;
use function DrevOps\Eddy\Tooling\dotenv_unset_var;
use function DrevOps\Eddy\Tooling\dotenv_write_var;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for dotenv_read(), dotenv_write_var() and dotenv_unset_var() helpers.
 */
#[CoversFunction('DrevOps\Eddy\Tooling\split_lines')]
#[CoversFunction('DrevOps\Eddy\Tooling\dotenv_read')]
#[CoversFunction('DrevOps\Eddy\Tooling\dotenv_write_var')]
#[CoversFunction('DrevOps\Eddy\Tooling\dotenv_unset_var')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersDotenvTest extends UnitTestCase {

  #[DataProvider('dataProviderDotenvRead')]
  public function testDotenvRead(?string $content, array $expected): void {
    $file = self::$tmp . '/dotenv_read_' . uniqid();
    if ($content !== NULL) {
      File::dump($file, $content);
    }

    $result = dotenv_read($file);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderDotenvRead(): \Iterator {
    yield 'file does not exist' => [
      'content' => NULL,
      'expected' => [],
    ];
    yield 'empty file' => [
      'content' => '',
      'expected' => [],
    ];
    yield 'single variable' => [
      'content' => "FOO=bar\n",
      'expected' => ['FOO' => 'bar'],
    ];
    yield 'multiple variables' => [
      'content' => "FOO=bar\nBAZ=qux\nWEBSERVER_PORT=8123\n",
      'expected' => ['FOO' => 'bar', 'BAZ' => 'qux', 'WEBSERVER_PORT' => '8123'],
    ];
    yield 'comments and blank lines are ignored' => [
      'content' => "# This is a comment\n\nFOO=bar\n  # indented comment\nBAZ=qux\n\n",
      'expected' => ['FOO' => 'bar', 'BAZ' => 'qux'],
    ];
    yield 'whitespace around keys and values is trimmed' => [
      'content' => "  FOO  =  bar  \n",
      'expected' => ['FOO' => 'bar'],
    ];
    yield 'value can be empty' => [
      'content' => "FOO=\n",
      'expected' => ['FOO' => ''],
    ];
    yield 'value with equals sign preserved' => [
      'content' => "URL=http://example.com/?a=1&b=2\n",
      'expected' => ['URL' => 'http://example.com/?a=1&b=2'],
    ];
    yield 'double quoted value is unquoted' => [
      'content' => "FOO=\"bar baz\"\n",
      'expected' => ['FOO' => 'bar baz'],
    ];
    yield 'single quoted value is unquoted' => [
      'content' => "FOO='bar baz'\n",
      'expected' => ['FOO' => 'bar baz'],
    ];
    yield 'mismatched quotes left intact' => [
      'content' => "FOO=\"bar'\n",
      'expected' => ['FOO' => '"bar\''],
    ];
    yield 'lines without equals are ignored' => [
      'content' => "FOO=bar\ninvalid_line\nBAZ=qux\n",
      'expected' => ['FOO' => 'bar', 'BAZ' => 'qux'],
    ];
    yield 'empty key is ignored' => [
      'content' => "=value\nFOO=bar\n",
      'expected' => ['FOO' => 'bar'],
    ];
    yield 'CRLF line endings' => [
      'content' => "FOO=bar\r\nBAZ=qux\r\n",
      'expected' => ['FOO' => 'bar', 'BAZ' => 'qux'],
    ];
    yield 'CR line endings' => [
      'content' => "FOO=bar\rBAZ=qux\r",
      'expected' => ['FOO' => 'bar', 'BAZ' => 'qux'],
    ];
    yield 'last assignment wins for duplicate keys' => [
      'content' => "FOO=first\nFOO=second\n",
      'expected' => ['FOO' => 'second'],
    ];
  }

  #[DataProvider('dataProviderDotenvWriteVar')]
  public function testDotenvWriteVar(?string $existing, string $key, string $value, string $expected): void {
    $file = self::$tmp . '/dotenv_write_' . uniqid();

    if ($existing === NULL) {
      $this->assertFileDoesNotExist($file);
    }
    else {
      File::dump($file, $existing);
    }

    dotenv_write_var($key, $value, $file);

    $this->assertFileExists($file);
    $this->assertSame($expected, File::read($file));
  }

  public static function dataProviderDotenvWriteVar(): \Iterator {
    yield 'creates the file' => [NULL, 'FOO', 'bar', "FOO=bar\n"];
    yield 'appends to an existing file' => ["EXISTING=value\n", 'NEW', 'data', "EXISTING=value\nNEW=data\n"];
    yield 'replaces an existing key in place' => ["FOO=old\nBAR=keep\n", 'FOO', 'new', "FOO=new\nBAR=keep\n"];
    yield 'preserves comments and blank lines' => [
      "# Top comment\n\nFOO=old\n\n# Another comment\nBAR=keep\n",
      'FOO',
      'new',
      "# Top comment\n\nFOO=new\n\n# Another comment\nBAR=keep\n",
    ];
    yield 'replaces the last match to agree with read' => ["FOO=first\nBAR=keep\nFOO=second\n", 'FOO', 'new', "FOO=first\nBAR=keep\nFOO=new\n"];
    yield 'file without a trailing newline' => ['EXISTING=value', 'NEW', 'data', "EXISTING=value\nNEW=data\n"];
  }

  public function testDotenvWriteVarAndReadRoundTrip(): void {
    $file = self::$tmp . '/dotenv_roundtrip_' . uniqid();

    dotenv_write_var('WEBSERVER_PORT', '8123', $file);
    dotenv_write_var('OTHER_VAR', 'hello', $file);
    dotenv_write_var('WEBSERVER_PORT', '8124', $file);

    $vars = dotenv_read($file);
    $this->assertSame(['WEBSERVER_PORT' => '8124', 'OTHER_VAR' => 'hello'], $vars);
  }

  #[DataProvider('dataProviderDotenvUnsetVar')]
  public function testDotenvUnsetVar(string $content, string $expected): void {
    $file = self::$tmp . '/dotenv_unset_' . uniqid();
    File::dump($file, $content);

    dotenv_unset_var('TUNNEL_URL', $file);

    $this->assertSame($expected, File::read($file));
    $this->assertArrayNotHasKey('TUNNEL_URL', dotenv_read($file));
  }

  public static function dataProviderDotenvUnsetVar(): \Iterator {
    yield 'only assignment' => [
      'content' => "TUNNEL_URL=https://abc.trycloudflare.com\n",
      'expected' => '',
    ];
    yield 'other variables keep their order' => [
      'content' => "WEBSERVER_PORT=8123\nTUNNEL_URL=https://abc.trycloudflare.com\nOTHER=1\n",
      'expected' => "WEBSERVER_PORT=8123\nOTHER=1\n",
    ];
    yield 'every duplicate is removed' => [
      'content' => "TUNNEL_URL=first\nOTHER=1\nTUNNEL_URL=second\n",
      'expected' => "OTHER=1\n",
    ];
    yield 'comments and blank lines are kept' => [
      'content' => "# TUNNEL_URL=commented\n\nTUNNEL_URL=active\nOTHER=1\n",
      'expected' => "# TUNNEL_URL=commented\n\nOTHER=1\n",
    ];
    yield 'whitespace around the key' => [
      'content' => "  TUNNEL_URL = active\nOTHER=1\n",
      'expected' => "OTHER=1\n",
    ];
    yield 'empty value' => [
      'content' => "TUNNEL_URL=\nOTHER=1\n",
      'expected' => "OTHER=1\n",
    ];
    yield 'keys sharing the name are kept' => [
      'content' => "TUNNEL_URL_PREVIOUS=a\nMY_TUNNEL_URL=b\nTUNNEL_URL=c\n",
      'expected' => "TUNNEL_URL_PREVIOUS=a\nMY_TUNNEL_URL=b\n",
    ];
    yield 'file without trailing newline' => [
      'content' => "OTHER=1\nTUNNEL_URL=active",
      'expected' => 'OTHER=1',
    ];
  }

  public function testDotenvUnsetVarLeavesFileWithoutKeyUntouched(): void {
    $file = self::$tmp . '/dotenv_unset_' . uniqid();
    File::dump($file, "OTHER=1\r\nMORE=2\r\n");
    // Writing to a read-only file fails, so the call passing proves that
    // nothing was rewritten.
    chmod($file, 0444);

    dotenv_unset_var('TUNNEL_URL', $file);

    $this->assertSame("OTHER=1\r\nMORE=2\r\n", File::read($file));
  }

  public function testDotenvUnsetVarMissingFile(): void {
    $file = self::$tmp . '/dotenv_unset_' . uniqid();

    dotenv_unset_var('TUNNEL_URL', $file);

    $this->assertFileDoesNotExist($file);
  }

  public function testDotenvUnsetVarWriteFailure(): void {
    $file = self::$tmp . '/dotenv_unset_' . uniqid();
    File::dump($file, "TUNNEL_URL=active\nOTHER=1\n");
    chmod($file, 0444);
    $this->mockQuit(1);
    set_error_handler(static fn(): bool => TRUE);

    try {
      $output = $this->capture(fn() => dotenv_unset_var('TUNNEL_URL', $file), 1);
    }
    finally {
      restore_error_handler();
    }

    $this->assertStringContainsString('Unable to write ' . $file, $output);
    $this->assertSame("TUNNEL_URL=active\nOTHER=1\n", File::read($file));
  }

}
