<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\resolve_webserver;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\resolve_webserver')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersResolveWebserverTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();

    self::envUnset('WEBSERVER_HOST');
    self::envUnset('WEBSERVER_PORT');
  }

  public function testDefaultsWhenNothingProvided(): void {
    $this->mockIsFile(['.env' => FALSE]);

    $resolved = resolve_webserver();

    $this->assertSame('localhost', $resolved['host']);
    $this->assertSame('default', $resolved['host_source']);
    $this->assertSame('8000', $resolved['port']);
    $this->assertSame('default', $resolved['port_source']);
  }

  public function testEnvOverridesDotenvAndDefault(): void {
    self::envSet('WEBSERVER_HOST', '0.0.0.0');
    self::envSet('WEBSERVER_PORT', '9001');
    $this->mockIsFile(['.env' => TRUE]);
    $this->mockFunction('file_get_contents', fn(): string => "WEBSERVER_HOST=ignored\nWEBSERVER_PORT=1234\n");

    $resolved = resolve_webserver();

    $this->assertSame('0.0.0.0', $resolved['host']);
    $this->assertSame('env', $resolved['host_source']);
    $this->assertSame('9001', $resolved['port']);
    $this->assertSame('env', $resolved['port_source']);
  }

  public function testDotenvUsedWhenEnvUnset(): void {
    $this->mockIsFile(['.env' => TRUE]);
    $this->mockFunction('file_get_contents', fn(): string => "WEBSERVER_HOST=internal\nWEBSERVER_PORT=8123\n");

    $resolved = resolve_webserver();

    $this->assertSame('internal', $resolved['host']);
    $this->assertSame('.env', $resolved['host_source']);
    $this->assertSame('8123', $resolved['port']);
    $this->assertSame('.env', $resolved['port_source']);
  }

  public function testAutoDiscoveryWritesPortAndUpdatesSource(): void {
    $this->mockIsFile(['.env' => FALSE]);
    $this->mockFunction('stream_socket_client', fn(): false => FALSE);

    $persisted = NULL;
    $this->mockFunction('file_put_contents', function (string $file, string $content) use (&$persisted): int {
      if (preg_match('/WEBSERVER_PORT=(\d+)/', $content, $m)) {
        $persisted = $m[1];
      }

      return strlen($content);
    });

    $resolved = resolve_webserver(auto_discover: TRUE);

    $this->assertSame('localhost', $resolved['host']);
    $this->assertSame('default', $resolved['host_source']);
    $this->assertSame('8000', $resolved['port']);
    $this->assertSame('.env', $resolved['port_source'], 'Auto-discovered port reports the dotenv file as its source after the write.');
    $this->assertSame('8000', $persisted, 'find_free_port() result was persisted to .env.');
  }

  #[DataProvider('dataProviderAutoDiscoverySkippedWhenDotenvHasPort')]
  public function testAutoDiscoverySkippedWhenDotenvHasPort(?string $env_port): void {
    if ($env_port !== NULL) {
      self::envSet('WEBSERVER_PORT', $env_port);
    }

    $this->mockIsFile(['.env' => TRUE]);
    $this->mockFunction('file_get_contents', fn(): string => "WEBSERVER_PORT=8123\n");

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

    $resolved = resolve_webserver(auto_discover: TRUE);

    $this->assertSame('8123', $resolved['port']);
    $this->assertSame('.env', $resolved['port_source']);
    $this->assertFalse($write_called, 'No write should occur when .env already supplies the port.');
    $this->assertFalse($probe_called, 'find_free_port() should not run when .env already supplies the port.');
  }

  public static function dataProviderAutoDiscoverySkippedWhenDotenvHasPort(): \Iterator {
    yield 'port in .env' => [NULL];
    yield 'port loaded from .env into the environment' => ['8123'];
  }

  public function testValidationFailsOnMalformedPort(): void {
    self::envSet('WEBSERVER_PORT', 'not-a-port');
    $this->mockIsFile(['.env' => FALSE]);

    $this->mockQuit(1);

    $output = $this->capture(fn(): array => resolve_webserver(), 1);

    $this->assertStringContainsString('Invalid WEBSERVER_PORT "not-a-port"', $output);
  }

  public function testValidationSkippedWhenDisabled(): void {
    self::envSet('WEBSERVER_PORT', 'not-a-port');
    $this->mockIsFile(['.env' => FALSE]);

    $resolved = resolve_webserver(validate_port: FALSE);

    $this->assertSame('not-a-port', $resolved['port'], 'With validation disabled, the malformed value is surfaced verbatim.');
    $this->assertSame('env', $resolved['port_source']);
  }

}
