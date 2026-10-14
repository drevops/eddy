<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use DrevOps\Eddy\Tests\Traits\WebserverTrait;
use DrevOps\Eddy\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;

/**
 * Functional tests for the webservers of several projects on the same machine.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p3')]
final class AutoPortDiscoveryTest extends UnitTestCase {

  use ProcessTrait;
  use WebserverTrait;

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
      @exec(sprintf('lsof -ti tcp:%s -sTCP:LISTEN | xargs kill -9 2>/dev/null', escapeshellarg($port)));
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

    $preset_port = self::webserverFreePort();
    File::dump($sut . '/.env', 'WEBSERVER_PORT=' . $preset_port . "\n# user comment\n");
    $original_env = File::read($sut . '/.env');

    $this->processCwd = $sut;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-start'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->startedPorts[] = $preset_port;
    $this->assertProcessAnyOutputContains('http://localhost:' . $preset_port);

    $this->assertSame($original_env, File::read($sut . '/.env'), '.env must not be rewritten when WEBSERVER_PORT is already set.');
  }

  public function testStopLeavesClientsRunning(): void {
    $sut = self::$tmp . '/proj_client_' . uniqid();
    $this->buildMinimalSut($sut);

    $this->processCwd = $sut;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-start'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();

    $port = $this->readEnvPort($sut);
    $this->startedPorts[] = $port;

    // A process that holds a client connection to the port, as a browser or a
    // test in the middle of a request does.
    $client = new Process(['php', '-r', sprintf('$c = @stream_socket_client("tcp://localhost:%s"); echo $c === FALSE ? "refused" : "connected"; sleep(60);', $port)]);
    $client->start();

    try {
      $client->waitUntil(fn(string $type, string $output): bool => $type === Process::OUT && $output !== '');
      $this->assertSame('connected', $client->getOutput());

      $this->processRun('php', [self::TOOLING_DIR . '/eddy-stop'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
      $this->assertProcessSuccessful();
      $this->assertProcessAnyOutputContains('ENVIRONMENT STOPPED');

      $this->assertTrue($client->isRunning(), 'stop leaves a client of the port running.');
      $this->assertFalse(self::webserverPortAccepts($port), 'stop stops the webserver.');
    }
    finally {
      $client->stop();
    }
  }

  public function testProjectsSharingPortLeaveEachOtherServerRunning(): void {
    $this->sut1 = self::$tmp . '/proj1_' . uniqid();
    $this->sut2 = self::$tmp . '/proj2_' . uniqid();

    $this->buildMinimalSut($this->sut1);
    $this->buildMinimalSut($this->sut2);

    // Both projects recorded the same port, as they do when one of them
    // discovers the port that the other one freed.
    $port = self::webserverFreePort();
    File::dump($this->sut1 . '/.env', 'WEBSERVER_PORT=' . $port . "\n");
    File::dump($this->sut2 . '/.env', 'WEBSERVER_PORT=' . $port . "\n");

    $this->processCwd = $this->sut2;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-start'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->startedPorts[] = $port;

    $this->processCwd = $this->sut1;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-stop'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputContains('Port ' . $port . ' is still in use by another process, which was left running:');
    $this->assertProcessAnyOutputContains('-t ' . $this->sut2 . '/build/web');
    $this->assertTrue(self::webserverPortAccepts($port), 'stop in one project leaves the webserver of the other project running.');

    $this->processRun('php', [self::TOOLING_DIR . '/eddy-start'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessFailed();
    $this->assertProcessAnyOutputContains('Port ' . $port . ' is in use by another process. Stop it, or set WEBSERVER_PORT in .env to a free port.');
    $this->assertProcessAnyOutputContains('-t ' . $this->sut2 . '/build/web');
    $this->assertTrue(self::webserverPortAccepts($port), 'start in one project leaves the webserver of the other project running.');

    $this->processCwd = $this->sut2;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-stop'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->assertProcessAnyOutputNotContains('left running');
    $this->assertFalse(self::webserverPortAccepts($port), 'stop stops the webserver of its own project.');
  }

  public function testWebserverStopStopsOnlyTheProjectServer(): void {
    $this->sut1 = self::$tmp . '/proj1_' . uniqid();
    $this->sut2 = self::$tmp . '/proj2_' . uniqid();

    $this->buildMinimalSut($this->sut1);
    $this->buildMinimalSut($this->sut2);

    $port = self::webserverFreePort();
    File::dump($this->sut2 . '/.env', 'WEBSERVER_PORT=' . $port . "\n");

    $this->processCwd = $this->sut2;
    $this->processRun('php', [self::TOOLING_DIR . '/eddy-start'], [], ['WEBSERVER_HOST' => 'localhost'], $this->defaultTimeout, $this->defaultIdleTimeout);
    $this->assertProcessSuccessful();
    $this->startedPorts[] = $port;

    self::webserverStop($this->sut1, $port);
    sleep(1);
    $this->assertTrue(self::webserverPortAccepts($port), 'Stopping the webserver of one project leaves the webserver of another project on the same port running.');

    self::webserverStop($this->sut2, $port);
    sleep(1);
    $this->assertFalse(self::webserverPortAccepts($port), 'Stopping the webserver of a project stops it.');
  }

  protected function buildMinimalSut(string $sut): void {
    $source_dir = dirname(__DIR__, 4) . '/.eddy/tooling/src';
    foreach (['helpers.php', 'eddy-start', 'eddy-stop'] as $file) {
      File::copy($source_dir . '/' . $file, $sut . '/' . self::TOOLING_DIR . '/' . $file);
    }

    self::webserverCreateDocroot($sut);
  }

  protected function readEnvPort(string $sut): string {
    $port = self::webserverDotenvPort($sut);
    $this->assertNotNull($port, sprintf('%s/.env records no WEBSERVER_PORT.', $sut));

    return $port;
  }

  protected function assertPortInRange(string $port): void {
    $port_int = (int) $port;
    $this->assertGreaterThanOrEqual(8000, $port_int);
    $this->assertLessThanOrEqual(8099, $port_int);
  }

}
