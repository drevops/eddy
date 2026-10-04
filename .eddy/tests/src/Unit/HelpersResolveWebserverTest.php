<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\resolve_webserver;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the resolve_webserver() helper.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\resolve_webserver')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersResolveWebserverTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';

    $this->envUnset('WEBSERVER_HOST');
    $this->envUnset('WEBSERVER_PORT');
  }

  public function testDefaultsWhenNothingProvided(): void {
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): bool => FALSE);

    $resolved = resolve_webserver();

    $this->assertSame('localhost', $resolved['host']);
    $this->assertSame('default', $resolved['host_source']);
    $this->assertSame('8000', $resolved['port']);
    $this->assertSame('default', $resolved['port_source']);
  }

  public function testEnvOverridesDotenvAndDefault(): void {
    $this->envSet('WEBSERVER_HOST', '0.0.0.0');
    $this->envSet('WEBSERVER_PORT', '9001');
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): bool => TRUE);
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(): string => "WEBSERVER_HOST=ignored\nWEBSERVER_PORT=1234\n");

    $resolved = resolve_webserver();

    $this->assertSame('0.0.0.0', $resolved['host']);
    $this->assertSame('env', $resolved['host_source']);
    $this->assertSame('9001', $resolved['port']);
    $this->assertSame('env', $resolved['port_source']);
  }

  public function testDotenvUsedWhenEnvUnset(): void {
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $f): bool => $f === '.env');
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(): string => "WEBSERVER_HOST=internal\nWEBSERVER_PORT=8123\n");

    $resolved = resolve_webserver();

    $this->assertSame('internal', $resolved['host']);
    $this->assertSame('.env', $resolved['host_source']);
    $this->assertSame('8123', $resolved['port']);
    $this->assertSame('.env', $resolved['port_source']);
  }

  public function testAutoDiscoveryWritesPortAndUpdatesSource(): void {
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): bool => FALSE);
    $this->registerMock('stream_socket_client', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $persisted = NULL;
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', function (string $f, string $body) use (&$persisted): int {
      if (preg_match('/WEBSERVER_PORT=(\d+)/', $body, $m)) {
        $persisted = $m[1];
      }

      return strlen($body);
    });

    $resolved = resolve_webserver(auto_discover: TRUE);

    $this->assertSame('localhost', $resolved['host']);
    $this->assertSame('default', $resolved['host_source']);
    $this->assertSame('8000', $resolved['port']);
    $this->assertSame('.env', $resolved['port_source'], 'Auto-discovered port reports the dotenv file as its source after the write.');
    $this->assertSame('8000', $persisted, 'find_free_port() result was persisted to .env.');
  }

  public function testAutoDiscoverySkippedWhenDotenvHasPort(): void {
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $f): bool => $f === '.env');
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(): string => "WEBSERVER_PORT=8123\n");

    $write_called = FALSE;
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', function () use (&$write_called): int {
      $write_called = TRUE;

      return 0;
    });
    $probe_called = FALSE;
    $this->registerMock('stream_socket_client', 'DrevOps\\Eddy\\DevTools', function () use (&$probe_called): false {
      $probe_called = TRUE;

      return FALSE;
    });

    $resolved = resolve_webserver(auto_discover: TRUE);

    $this->assertSame('8123', $resolved['port']);
    $this->assertSame('.env', $resolved['port_source']);
    $this->assertFalse($write_called, 'No write should occur when .env already supplies the port.');
    $this->assertFalse($probe_called, 'find_free_port() should not run when .env already supplies the port.');
  }

  public function testValidationFailsOnMalformedPort(): void {
    $this->envSet('WEBSERVER_PORT', 'not-a-port');
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): bool => FALSE);

    $this->mockQuit(1);

    ob_start();
    try {
      resolve_webserver();
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Invalid WEBSERVER_PORT "not-a-port"', $output);
    }
  }

  public function testValidationSkippedWhenDisabled(): void {
    $this->envSet('WEBSERVER_PORT', 'not-a-port');
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): bool => FALSE);

    $resolved = resolve_webserver(validate_port: FALSE);

    $this->assertSame('not-a-port', $resolved['port'], 'With validation disabled, the malformed value is surfaced verbatim.');
    $this->assertSame('env', $resolved['port_source']);
  }

}
