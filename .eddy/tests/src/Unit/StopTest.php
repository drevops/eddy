<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-stop' command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class StopTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testStopDefaultPortWhenNoEnvAndNoDotenv(): void {
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $this->mockPassthru([
      'cmd' => "lsof -ti:'8000' 2>/dev/null | xargs kill -9 2>/dev/null",
    ]);
    $this->mockSleep();

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('STOP ENVIRONMENT', $output);
    $this->assertStringContainsString('Stopping previously started services on port 8000', $output);
    $this->assertStringContainsString('Services stopped on port 8000', $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);
  }

  public function testStopCustomPortFromEnv(): void {
    $this->envSet('WEBSERVER_PORT', '9000');

    $this->mockPassthru([
      'cmd' => "lsof -ti:'9000' 2>/dev/null | xargs kill -9 2>/dev/null",
    ]);
    $this->mockSleep();

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('STOP ENVIRONMENT', $output);
    $this->assertStringContainsString('Services stopped on port 9000', $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);
  }

  public function testStopReadsPortFromDotenvWhenEnvUnset(): void {
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $file): bool => $file === '.env');
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(): string => "WEBSERVER_PORT=8123\n");

    $this->mockPassthru([
      'cmd' => "lsof -ti:'8123' 2>/dev/null | xargs kill -9 2>/dev/null",
    ]);
    $this->mockSleep();

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-stop';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('Services stopped on port 8123', $output);
    $this->assertStringContainsString('ENVIRONMENT STOPPED', $output);
  }

}
