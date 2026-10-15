<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\resolve_webdriver_port;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\resolve_webdriver_port')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersResolveWebdriverPortTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();

    self::envUnset('WEBDRIVER_PORT');
  }

  public function testDefaultsWhenNothingProvided(): void {
    $this->mockFunction('file_exists', fn(): bool => FALSE);

    $resolved = resolve_webdriver_port();

    $this->assertSame('4444', $resolved['port']);
    $this->assertSame('default', $resolved['port_source']);
  }

  public function testEnvOverridesDotenvAndDefault(): void {
    self::envSet('WEBDRIVER_PORT', '9515');
    $this->mockFunction('file_exists', fn(): bool => TRUE);
    $this->mockFunction('file_get_contents', fn(): string => "WEBDRIVER_PORT=1234\n");

    $resolved = resolve_webdriver_port();

    $this->assertSame('9515', $resolved['port']);
    $this->assertSame('env', $resolved['port_source']);
  }

  public function testDotenvUsedWhenEnvUnset(): void {
    $this->mockFunction('file_exists', fn(string $file): bool => $file === '.env');
    $this->mockFunction('file_get_contents', fn(): string => "WEBDRIVER_PORT=4500\n");

    $resolved = resolve_webdriver_port();

    $this->assertSame('4500', $resolved['port']);
    $this->assertSame('.env', $resolved['port_source']);
  }

  public function testAutoDiscoveryWritesPortAndUpdatesSource(): void {
    $this->mockFunction('file_exists', fn(): bool => FALSE);
    $this->mockFunction('stream_socket_client', fn(): false => FALSE);

    $persisted = NULL;
    $this->mockFunction('file_put_contents', function (string $file, string $content) use (&$persisted): int {
      if (preg_match('/WEBDRIVER_PORT=(\d+)/', $content, $m)) {
        $persisted = $m[1];
      }

      return strlen($content);
    });

    $resolved = resolve_webdriver_port(auto_discover: TRUE);

    $this->assertSame('4444', $resolved['port']);
    $this->assertSame('.env', $resolved['port_source'], 'Auto-discovered port reports the dotenv file as its source after the write.');
    $this->assertSame('4444', $persisted, 'find_free_port() result was persisted to .env.');
  }

  public function testAutoDiscoverySkippedWhenDotenvHasPort(): void {
    $this->mockFunction('file_exists', fn(string $file): bool => $file === '.env');
    $this->mockFunction('file_get_contents', fn(): string => "WEBDRIVER_PORT=4500\n");

    $write_called = FALSE;
    $this->mockFunction('file_put_contents', function () use (&$write_called): int {
      $write_called = TRUE;

      return 0;
    });
    $probe_called = FALSE;
    $this->mockFunction('stream_socket_client', function () use (&$probe_called): false {
      $probe_called = TRUE;

      return FALSE;
    });

    $resolved = resolve_webdriver_port(auto_discover: TRUE);

    $this->assertSame('4500', $resolved['port']);
    $this->assertSame('.env', $resolved['port_source']);
    $this->assertFalse($write_called, 'No write should occur when .env already supplies the port.');
    $this->assertFalse($probe_called, 'find_free_port() should not run when .env already supplies the port.');
  }

  public function testValidationFailsOnMalformedPort(): void {
    self::envSet('WEBDRIVER_PORT', 'not-a-port');
    $this->mockFunction('file_exists', fn(): bool => FALSE);

    $this->mockQuit(1);

    $output = $this->capture(fn(): array => resolve_webdriver_port(), 1);

    $this->assertStringContainsString('Invalid WEBDRIVER_PORT "not-a-port"', $output);
  }

  public function testValidationSkippedWhenDisabled(): void {
    self::envSet('WEBDRIVER_PORT', 'not-a-port');
    $this->mockFunction('file_exists', fn(): bool => FALSE);

    $resolved = resolve_webdriver_port(validate_port: FALSE);

    $this->assertSame('not-a-port', $resolved['port'], 'With validation disabled, the malformed value is surfaced verbatim.');
    $this->assertSame('env', $resolved['port_source']);
  }

}
