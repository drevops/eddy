<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-browser-stop' command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class BrowserStopTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
    $this->envUnset('WEBDRIVER_PORT');
    $this->envUnset('WEBDRIVER_BACKEND');
  }

  #[DataProvider('dataProviderBrowserStop')]
  public function testBrowserStop(array $env, ?string $dotenv, bool $docker, string $expected_port): void {
    foreach ($env as $name => $value) {
      $this->envSet($name, $value);
    }

    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $file): bool => $dotenv !== NULL && $file === '.env');
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(string $file): string|false => $file === '.env' && $dotenv !== NULL ? $dotenv : FALSE);

    $written = FALSE;
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', function (string $file, string $contents) use (&$written): int {
      $written = TRUE;

      return strlen($contents);
    });

    $probed = FALSE;
    $this->registerMock('stream_socket_client', 'DrevOps\\Eddy\\DevTools', function () use (&$probed): false {
      $probed = TRUE;

      return FALSE;
    });

    $this->mockCommandAvailable('docker', $docker);

    $commands = [];
    if ($docker) {
      $commands[] = ['cmd' => sprintf('docker rm -f %s >/dev/null 2>&1', escapeshellarg('selenium-' . $expected_port))];
    }
    $commands[] = ['cmd' => sprintf('lsof -ti:%s 2>/dev/null | xargs kill -9 2>/dev/null', escapeshellarg($expected_port))];
    $this->mockPassthruMultiple($commands);

    $this->mockSleep();

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-browser-stop';
    $output = (string) ob_get_clean();

    $this->assertStringContainsString('STOP BROWSER', $output);
    $this->assertStringContainsString('Browser stopped on port ' . $expected_port . '.', $output);
    $this->assertStringContainsString('BROWSER STOPPED', $output);
    $this->assertFalse($written, 'Stopping must not persist a port to .env.');
    $this->assertFalse($probed, 'Stopping must target the resolved port rather than discover a free one.');
  }

  public static function dataProviderBrowserStop(): \Iterator {
    yield 'default port, docker installed' => [
      'env' => [],
      'dotenv' => NULL,
      'docker' => TRUE,
      'expected_port' => '4444',
    ];
    yield 'default port, docker not installed' => [
      'env' => [],
      'dotenv' => NULL,
      'docker' => FALSE,
      'expected_port' => '4444',
    ];
    yield 'port from .env' => [
      'env' => [],
      'dotenv' => "WEBDRIVER_PORT=4445\n",
      'docker' => TRUE,
      'expected_port' => '4445',
    ];
    yield 'port from env overrides .env' => [
      'env' => ['WEBDRIVER_PORT' => '4450'],
      'dotenv' => "WEBDRIVER_PORT=4445\n",
      'docker' => TRUE,
      'expected_port' => '4450',
    ];
    yield 'chromedriver backend still removes the container' => [
      'env' => ['WEBDRIVER_BACKEND' => 'chromedriver'],
      'dotenv' => "WEBDRIVER_PORT=4445\n",
      'docker' => TRUE,
      'expected_port' => '4445',
    ];
    yield 'selenium backend without docker still frees the port' => [
      'env' => ['WEBDRIVER_BACKEND' => 'selenium'],
      'dotenv' => "WEBDRIVER_PORT=4445\n",
      'docker' => FALSE,
      'expected_port' => '4445',
    ];
    yield 'unknown backend is not validated' => [
      'env' => ['WEBDRIVER_BACKEND' => 'firefox'],
      'dotenv' => "WEBDRIVER_PORT=4445\n",
      'docker' => TRUE,
      'expected_port' => '4445',
    ];
  }

  public function testBrowserStopFailsOnInvalidPort(): void {
    $this->envSet('WEBDRIVER_PORT', '99999');
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);
    $this->mockCommandAvailable('docker', TRUE);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-browser-stop';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = (string) ob_get_clean();
    }

    $this->assertStringContainsString('Invalid WEBDRIVER_PORT "99999"', $output);
    $this->assertStringNotContainsString('BROWSER STOPPED', $output);
  }

}
