<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class LoginTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();

    self::envUnset('WEBSERVER_HOST');
    self::envUnset('WEBSERVER_PORT');
    self::envUnset('LOGIN_QRCODE');
  }

  /**
   * @param array<string, string> $env
   *   The environment variables to set.
   */
  #[DataProvider('dataProviderLogin')]
  public function testLogin(array $env, string $dotenv, string $expected_site_url, bool $expected_qrcode): void {
    self::envSetMultiple($env);
    $this->mockProject($dotenv);

    $link = $expected_site_url . '/user/reset/1/1790000000/abc/login';
    $responses = self::drushResponses($expected_site_url, $link . "\n");

    if ($expected_qrcode) {
      $this->mockCommandAvailable('qrencode', TRUE);
      $responses[] = ['cmd' => 'qrencode -t ANSIUTF8 ' . escapeshellarg($link), 'output' => '[QR-CODE]'];
    }

    $this->mockPassthruMultiple($responses);

    $output = $this->runCommand('eddy-login');

    $this->assertSame($link . PHP_EOL . ($expected_qrcode ? '[QR-CODE]' : ''), $output);
  }

  public static function dataProviderLogin(): \Iterator {
    yield 'default site URL' => [[], '', 'http://localhost:8000', FALSE];
    yield 'host and port from the environment' => [['WEBSERVER_HOST' => '0.0.0.0', 'WEBSERVER_PORT' => '9000'], '', 'http://0.0.0.0:9000', FALSE];
    yield 'port from .env' => [[], "WEBSERVER_PORT=8123\n", 'http://localhost:8123', FALSE];
    yield 'port from the environment over .env' => [['WEBSERVER_PORT' => '9000'], "WEBSERVER_PORT=8123\n", 'http://localhost:9000', FALSE];
    yield 'tunnel URL from .env' => [[], "WEBSERVER_PORT=8000\nTUNNEL_URL=https://abc.trycloudflare.com\n", 'https://abc.trycloudflare.com', FALSE];
    yield 'QR code from the environment' => [['LOGIN_QRCODE' => '1'], '', 'http://localhost:8000', TRUE];
    yield 'QR code from .env' => [[], "LOGIN_QRCODE=1\n", 'http://localhost:8000', TRUE];
    yield 'QR code for any value that is set' => [['LOGIN_QRCODE' => '0'], '', 'http://localhost:8000', TRUE];
    yield 'QR code of the tunnel link' => [['LOGIN_QRCODE' => '1'], "TUNNEL_URL=https://abc.trycloudflare.com\n", 'https://abc.trycloudflare.com', TRUE];
    yield 'no QR code for an empty value' => [['LOGIN_QRCODE' => ''], '', 'http://localhost:8000', FALSE];
  }

  public function testLoginFailsWhenDrushFails(): void {
    self::envSet('LOGIN_QRCODE', '1');
    $this->mockProject('');
    $this->mockPassthruMultiple(self::drushResponses('http://localhost:8000', "[error]  Command user:login needs a higher bootstrap level to run.\n", 1));
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-login', 1);

    $this->assertStringContainsString('Command user:login needs a higher bootstrap level to run.', $output);
    $this->assertStringContainsString('Drush command failed', $output);
  }

  public function testLoginFailsOnInvalidPort(): void {
    self::envSet('WEBSERVER_PORT', 'abc');
    $this->mockProject('');
    $this->mockPassthruNever();
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-login', 1);

    $this->assertStringContainsString('Invalid WEBSERVER_PORT "abc"', $output);
  }

  public function testLoginPrintsLinkBeforeMissingQrencodeFails(): void {
    self::envSet('LOGIN_QRCODE', '1');
    $this->mockProject('');
    $this->mockCommandAvailable('qrencode', FALSE);

    $link = 'http://localhost:8000/user/reset/1/1790000000/abc/login';
    $this->mockPassthruMultiple(self::drushResponses('http://localhost:8000', $link . "\n"));
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-login', 1);

    $this->assertStringStartsWith($link . PHP_EOL, $output);
    $this->assertStringContainsString("Command 'qrencode' is not available", $output);
  }

  /**
   * Mock the project root and its '.env'.
   *
   * @param string $dotenv
   *   The content of '.env', or an empty string for a project without one.
   */
  protected function mockProject(string $dotenv): void {
    $this->mockFunction('getcwd', fn(): string => self::PROJECT_ROOT);
    $this->mockIsFile(['.env' => $dotenv !== '']);
    $this->mockFunction('file_get_contents', fn(string $file): string => $file === '.env' ? $dotenv : '');
  }

  /**
   * @return array<int, array{cmd: string, output: string, result_code: int}>
   *   The Drush commands that generate a link on Drush 13, in order.
   */
  protected static function drushResponses(string $site_url, string $uli_output, int $uli_result_code = 0): array {
    $prefix = 'build/vendor/bin/drush -r ' . escapeshellarg(self::PROJECT_ROOT . '/build/web') . ' -y ';

    return [
      ['cmd' => $prefix . 'status --field=drush-version', 'output' => "13.8.0\n", 'result_code' => 0],
      ['cmd' => $prefix . 'uli -l ' . escapeshellarg($site_url) . ' --no-browser', 'output' => $uli_output, 'result_code' => $uli_result_code],
    ];
  }

}
