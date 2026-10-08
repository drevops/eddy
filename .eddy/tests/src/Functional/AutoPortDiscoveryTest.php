<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use DrevOps\Eddy\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for auto-port discovery across multiple projects.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p3')]
final class AutoPortDiscoveryTest extends UnitTestCase {

  use ProcessTrait;

  protected const string TOOLING_DIR = 'vendor/drevops/eddy-tooling/src';

  protected int $defaultTimeout = 30;

  protected int $defaultIdleTimeout = 15;

  protected string $sut1 = '';

  protected string $sut2 = '';

  /**
   * Ports the test has started PHP servers on; tearDown kills them.
   *
   * @var array<int, string>
   */
  protected array $startedPorts = [];

  protected function tearDown(): void {
    foreach ($this->startedPorts as $port) {
      // phpcs:ignore
      @exec(sprintf('lsof -ti:%s | xargs kill -9 2>/dev/null', escapeshellarg($port)));
    }
    sleep(1);

    $this->processTearDown();

    parent::tearDown();
  }

  public function testTwoProjectsAutoDiscoverDistinctPorts(): void {
    $this->sut1 = self::$tmp . '/proj1_' . uniqid();
    $this->sut2 = self::$tmp . '/proj2_' . uniqid();

    $this->buildMinimalSut($this->sut1);
    $this->buildMinimalSut($this->sut2);

    $this->processCwd = $this->sut1;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-start'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT READY');

    $port1 = $this->readEnvPort($this->sut1);
    $this->startedPorts[] = $port1;
    $this->assertPortInRange($port1);
    $this->assertProcessAnyOutputContains('http://localhost:' . $port1);

    // Wait for the backgrounded server to become ready before probing.
    sleep(1);

    $this->processCwd = $this->sut2;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-start'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT READY');

    $port2 = $this->readEnvPort($this->sut2);
    $this->startedPorts[] = $port2;
    $this->assertPortInRange($port2);
    $this->assertNotSame($port1, $port2, 'Second project must auto-discover a port different from the first project.');
    $this->assertProcessAnyOutputContains('http://localhost:' . $port2);

    $this->processCwd = $this->sut2;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-stop'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('ENVIRONMENT STOPPED');

    $this->processRun('php', [self::TOOLING_DIR . '/eddy-start'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('http://localhost:' . $port2);
    $this->assertSame($port2, $this->readEnvPort($this->sut2), '.env port must be reused after stop/restart.');

    $this->processCwd = $this->sut1;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-stop'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertSame($port1, $this->readEnvPort($this->sut1), 'stop must not modify .env.');

    $this->processCwd = $this->sut2;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-stop'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertSame($port2, $this->readEnvPort($this->sut2), 'stop must not modify .env.');
  }

  public function testExistingDotenvPortIsHonoredAndNotRewritten(): void {
    $sut = self::$tmp . '/proj_preset_' . uniqid();
    $this->buildMinimalSut($sut);

    $preset_port = $this->pickFreePortForTest();
    file_put_contents($sut . '/.env', 'WEBSERVER_PORT=' . $preset_port . "\n# user comment\n");
    $original_env = file_get_contents($sut . '/.env');

    $this->processCwd = $sut;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-start'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->startedPorts[] = $preset_port;
    $this->assertProcessAnyOutputContains('http://localhost:' . $preset_port);

    $this->assertSame($original_env, file_get_contents($sut . '/.env'), '.env must not be rewritten when WEBSERVER_PORT is already set.');
  }

  protected function buildMinimalSut(string $sut): void {
    mkdir($sut . '/' . self::TOOLING_DIR, 0755, TRUE);
    mkdir($sut . '/build/web', 0755, TRUE);

    $source_dir = dirname(__DIR__, 4) . '/.eddy/tooling/src';
    foreach (['helpers.php', 'eddy-start', 'eddy-stop'] as $file) {
      copy($source_dir . '/' . $file, $sut . '/' . self::TOOLING_DIR . '/' . $file);
    }

    file_put_contents($sut . '/build/web/.ht.router.php', "<?php return FALSE;\n");
    file_put_contents($sut . '/build/web/index.html', "OK\n");
  }

  protected function readEnvPort(string $sut): string {
    $contents = @file_get_contents($sut . '/.env');
    $this->assertNotFalse($contents, 'Expected .env file to exist at ' . $sut . '/.env');
    if (!preg_match('/^WEBSERVER_PORT=(\d+)$/m', $contents, $matches)) {
      $this->fail('.env does not contain WEBSERVER_PORT line. Contents: ' . $contents);
    }

    return $matches[1];
  }

  protected function assertPortInRange(string $port): void {
    $port_int = (int) $port;
    $this->assertGreaterThanOrEqual(8000, $port_int);
    $this->assertLessThanOrEqual(8099, $port_int);
  }

  /**
   * Pick a free port in the 9000+ range so it does not collide with the
   * 8000-8099 auto-discovery window used by the other test scenarios.
   */
  protected function pickFreePortForTest(): string {
    for ($port = 9000; $port < 9100; $port++) {
      $sock = @stream_socket_server(sprintf('tcp://127.0.0.1:%d', $port));
      if ($sock !== FALSE) {
        fclose($sock);

        return (string) $port;
      }
    }

    $this->fail('Could not find a free test port in 9000-9099.');
  }

}
