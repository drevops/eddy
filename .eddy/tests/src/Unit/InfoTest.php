<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class InfoTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();

    self::envUnset('WEBSERVER_HOST');
    self::envUnset('WEBSERVER_PORT');
    self::envUnset('WEBDRIVER_PORT');
    self::envUnset('WEBDRIVER_BACKEND');
    self::envUnset('WEBDRIVER_HOST');
    self::envUnset('DRUPAL_PROFILE');
  }

  /**
   * Set up the standard mocks for an info run.
   *
   * @param array<string, string> $shell_exec_map
   *   The output of shell_exec() keyed by a substring of the command. The
   *   first key found in the command selects the output, and a literal '*'
   *   key is the default for a command no other key matches.
   * @param array<string, bool> $files
   *   Whether each path exists, keyed by path, for is_file() and is_dir().
   * @param string[] $info_files
   *   Files returned from glob('*.info.yml').
   * @param array<string, string> $file_contents
   *   The contents file_get_contents() returns, keyed by path.
   * @param string|false $command_php_path
   *   Path returned by command_path('php'); FALSE means not found.
   */
  protected function setupInfoMocks(
    array $shell_exec_map,
    array $files = [],
    array $info_files = [],
    array $file_contents = [],
    string|false $command_php_path = '/usr/bin/php',
  ): void {
    $this->mockFunction('getcwd', fn(): string => self::PROJECT_ROOT);

    $this->mockFunction('shell_exec', function (string $command) use ($shell_exec_map): string {
      foreach ($shell_exec_map as $needle => $output) {
        if ($needle !== '*' && str_contains($command, $needle)) {
          return $output;
        }
      }

      return $shell_exec_map['*'] ?? '';
    });

    $this->mockFunction('is_file', fn(string $path): bool => $files[$path] ?? FALSE);
    $this->mockFunction('is_dir', fn(string $path): bool => $files[$path] ?? FALSE);
    $this->mockFunction('glob', fn(): array => $info_files);
    $this->mockFunction('file_get_contents', fn(string $file): string => $file_contents[$file] ?? '');

    $this->mockCommandPaths(['php' => $command_php_path]);
  }

  public function testInfoFullyPopulatedEnvironment(): void {
    self::envSet('WEBSERVER_HOST', 'example.com');
    self::envSet('WEBSERVER_PORT', '9001');
    self::envSet('DRUPAL_PROFILE', 'minimal');

    $drush_bin = 'build/vendor/bin/drush';

    $this->setupInfoMocks(
      shell_exec_map: [
        'php -v' => "PHP 8.3.14 (cli) (built: ...)\n",
        'composer --version' => "Composer version 2.7.7 2024-06-10 22:11:12\n",
        'node --version' => "v20.18.0\n",
        'npm --version' => "10.8.2\n",
        '--version' => "Drush Commandline Tool 13.3.0\n",
        'status --field=drupal-version' => "11.3.11\n",
        'lsof -ti' => "1234\n",
        'ps -p 1234' => "php -S example.com:9001 -t /test/project/build/web /test/project/build/web/.ht.router.php\n",
      ],
      files: [
        $drush_bin => TRUE,
        self::PROJECT_ROOT . '/build' => TRUE,
      ],
      info_files: ['your_extension.info.yml'],
    );

    $output = $this->runInfo();

    $this->assertStringContainsString('Drupal version:     11.3.11', $output);
    $this->assertStringContainsString('PHP version:        8.3.14 (/usr/bin/php)', $output);
    $this->assertStringContainsString('Composer:           2.7.7', $output);
    $this->assertStringContainsString('Drush:              13.3.0', $output);
    $this->assertStringContainsString('Node / npm:         20.18.0 / 10.8.2', $output);
    $this->assertStringContainsString('Webserver host:     example.com (env)', $output);
    $this->assertStringContainsString('Webserver port:     9001 (env)', $output);
    $this->assertStringContainsString('Site URL:           http://example.com:9001', $output);
    $this->assertStringContainsString('XDebug:             disabled', $output);
    $this->assertStringContainsString('Build dir:          /test/project/build', $output);
    $this->assertStringContainsString('Database:           /tmp/site_your_extension.sqlite', $output);
    $this->assertStringContainsString('Drupal profile:     minimal', $output);
    $this->assertStringContainsString('ENVIRONMENT INFO', $output);
  }

  public function testInfoReadsPortFromDotenvWithFileSource(): void {
    $this->setupInfoMocks(
      shell_exec_map: [
        'php -v' => "PHP 8.3.14\n",
        'composer --version' => '',
        'node --version' => '',
        'npm --version' => '',
        'lsof -ti' => '',
      ],
      files: ['.env' => TRUE],
      info_files: [],
      file_contents: ['.env' => "WEBSERVER_PORT=8123\n"],
    );

    $output = $this->runInfo();

    $this->assertStringContainsString('Webserver host:     localhost (default)', $output);
    $this->assertStringContainsString('Webserver port:     8123 (.env)', $output);
    $this->assertStringContainsString('Site URL:           http://localhost:8123', $output);
  }

  public function testInfoSiteUrlPrefersTunnelUrl(): void {
    $tunnel_url = 'https://random-words.trycloudflare.com';

    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: ['.env' => TRUE],
      info_files: [],
      file_contents: ['.env' => "WEBSERVER_PORT=8000\nTUNNEL_URL=" . $tunnel_url . "\n"],
    );

    $output = $this->runInfo();

    $this->assertStringContainsString('Webserver port:     8000 (.env)', $output);
    $this->assertStringContainsString('Site URL:           ' . $tunnel_url, $output);
  }

  public function testInfoSiteUrlFieldPrefersTunnelUrl(): void {
    $tunnel_url = 'https://random-words.trycloudflare.com';

    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: ['.env' => TRUE],
      info_files: [],
      file_contents: ['.env' => 'TUNNEL_URL=' . $tunnel_url . "\n"],
    );

    $output = $this->runInfoField('site-url', 0);

    $this->assertSame($tunnel_url . PHP_EOL, $output);
  }

  public function testInfoFallsBackToDefaultsWhenNothingResolves(): void {
    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: [],
      info_files: [],
      command_php_path: FALSE,
    );

    $output = $this->runInfo();

    $this->assertStringContainsString('Drupal version:     -', $output);
    $this->assertStringContainsString('PHP version:        -', $output);
    $this->assertStringContainsString('Composer:           -', $output);
    $this->assertStringContainsString('Drush:              -', $output);
    $this->assertStringContainsString('Node / npm:         - / -', $output);
    $this->assertStringContainsString('Webserver host:     localhost (default)', $output);
    $this->assertStringContainsString('Webserver port:     8000 (default)', $output);
    $this->assertStringContainsString('Site URL:           http://localhost:8000', $output);
    $this->assertStringContainsString('XDebug:             -', $output);
    $this->assertStringContainsString('Build dir:          -', $output);
    $this->assertStringContainsString('Database:           -', $output);
    $this->assertStringContainsString('Drupal profile:     standard', $output);
  }

  public function testInfoOmitsPhpPathWhenPhpPathIsUnknown(): void {
    $this->setupInfoMocks(
      shell_exec_map: [
        'php -v' => "PHP 8.3.14\n",
        '*' => '',
      ],
      files: [],
      info_files: [],
      command_php_path: FALSE,
    );

    $output = $this->runInfo();

    $this->assertStringContainsString("PHP version:        8.3.14\n", $output);
    $this->assertStringNotContainsString('PHP version:        8.3.14 (', $output);
  }

  #[DataProvider('dataProviderInfoXdebugStateDetection')]
  public function testInfoXdebugStateDetection(?string $listener, string $expected_state): void {
    $this->setupInfoMocks(
      shell_exec_map: [
        'lsof -ti' => $listener === NULL ? '' : "1234\n",
        'ps -p 1234' => $listener . "\n",
        '*' => '',
      ],
      files: [],
      info_files: [],
    );

    $output = $this->runInfo();

    $this->assertStringContainsString('XDebug:             ' . $expected_state, $output);
  }

  public static function dataProviderInfoXdebugStateDetection(): \Iterator {
    yield 'no server listening' => ['listener' => NULL, 'expected_state' => '-'];
    yield 'server running without xdebug' => [
      'listener' => self::webserverCommandLine(self::PROJECT_ROOT),
      'expected_state' => 'disabled',
    ];
    yield 'server running with xdebug' => [
      'listener' => 'php -d xdebug.mode=debug -d xdebug.start_with_request=yes -S localhost:8000 -t /test/project/build/web /test/project/build/web/.ht.router.php',
      'expected_state' => 'enabled',
    ];
    yield 'only the server of another project, with xdebug' => [
      'listener' => 'php -d xdebug.mode=debug -d xdebug.start_with_request=yes -S localhost:8000 -t /www/other/build/web /www/other/build/web/.ht.router.php',
      'expected_state' => '-',
    ];
  }

  #[DataProvider('dataProviderInfoWebdriverPort')]
  public function testInfoWebdriverPort(bool $has_functional_javascript): void {
    self::envSet('WEBDRIVER_PORT', '4455');

    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: [self::PROJECT_ROOT . '/tests/src/FunctionalJavascript' => $has_functional_javascript],
      info_files: [],
    );

    $output = $this->runInfo();

    $this->assertSame($has_functional_javascript, str_contains($output, "\nWebDriver port:     4455\n"), 'WebDriver port line presence mismatch.');
    $this->assertSame($has_functional_javascript, str_contains($output, 'WebDriver'), 'WebDriver text presence mismatch.');
    $this->assertStringContainsString("XDebug:             -\n", $output);
    $this->assertStringContainsString("\n\nBuild dir:          -\n", $output);
  }

  public static function dataProviderInfoWebdriverPort(): \Iterator {
    yield 'FunctionalJavascript tests' => [TRUE];
    yield 'no FunctionalJavascript tests' => [FALSE];
  }

  public function testInfoDrushDrupalVersionFallbackWhenStatusReturnsNothing(): void {
    // The drush binary exists but `drush status --field=drupal-version`
    // returns empty, for example before the site is installed.
    $drush_bin = 'build/vendor/bin/drush';

    $this->setupInfoMocks(
      shell_exec_map: [
        '--version' => "Drush Commandline Tool 13.3.0\n",
        'status --field=drupal-version' => '',
        '*' => '',
      ],
      files: [
        $drush_bin => TRUE,
      ],
      info_files: [],
    );

    $output = $this->runInfo();

    $this->assertStringContainsString('Drush:              13.3.0', $output);
    $this->assertStringContainsString('Drupal version:     -', $output);
  }

  #[DataProvider('dataProviderInfoFieldModeKnownFields')]
  public function testInfoFieldModeKnownFields(string $field, array $shell_exec_map, array $files, string $expected): void {
    $this->setupInfoMocks(
      shell_exec_map: $shell_exec_map,
      files: $files,
      info_files: ['your_extension.info.yml'],
    );

    $output = $this->runInfoField($field, 0);

    $this->assertSame($expected . PHP_EOL, $output);
  }

  public static function dataProviderInfoFieldModeKnownFields(): \Iterator {
    yield 'xdebug enabled' => [
      'field' => 'xdebug',
      'shell_exec_map' => ['lsof -ti' => "1234\n", 'ps -p 1234' => "php -d xdebug.mode=debug -S localhost:8000 -t /test/project/build/web\n", '*' => ''],
      'files' => [],
      'expected' => 'enabled',
    ];
    yield 'xdebug disabled' => [
      'field' => 'xdebug',
      'shell_exec_map' => ['lsof -ti' => "1234\n", 'ps -p 1234' => "php -S localhost:8000 -t /test/project/build/web\n", '*' => ''],
      'files' => [],
      'expected' => 'disabled',
    ];
    yield 'xdebug only on the server of another project' => [
      'field' => 'xdebug',
      'shell_exec_map' => ['lsof -ti' => "1234\n", 'ps -p 1234' => "php -d xdebug.mode=debug -S localhost:8000 -t /www/other/build/web\n", '*' => ''],
      'files' => [],
      'expected' => '-',
    ];
    yield 'xdebug no server' => [
      'field' => 'xdebug',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => '-',
    ];
    yield 'webserver-port default' => [
      'field' => 'webserver-port',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => '8000',
    ];
    yield 'webserver-host default' => [
      'field' => 'webserver-host',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => 'localhost',
    ];
    yield 'site-url' => [
      'field' => 'site-url',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => 'http://localhost:8000',
    ];
    yield 'webdriver-port without FunctionalJavascript tests' => [
      'field' => 'webdriver-port',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => '4444',
    ];
    yield 'webdriver-backend default' => [
      'field' => 'webdriver-backend',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => 'chromedriver',
    ];
    yield 'webdriver-host unset' => [
      'field' => 'webdriver-host',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => '',
    ];
    yield 'drupal-profile default' => [
      'field' => 'drupal-profile',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => 'standard',
    ];
    yield 'build-dir present' => [
      'field' => 'build-dir',
      'shell_exec_map' => ['*' => ''],
      'files' => [self::PROJECT_ROOT . '/build' => TRUE],
      'expected' => self::PROJECT_ROOT . '/build',
    ];
    yield 'build-dir absent' => [
      'field' => 'build-dir',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => '-',
    ];
    yield 'database' => [
      'field' => 'database',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => '/tmp/site_your_extension.sqlite',
    ];
    yield 'php-version' => [
      'field' => 'php-version',
      'shell_exec_map' => ['php -v' => "PHP 8.3.14 (cli)\n", '*' => ''],
      'files' => [],
      'expected' => '8.3.14',
    ];
    yield 'composer' => [
      'field' => 'composer',
      'shell_exec_map' => ['composer --version' => "Composer version 2.7.7\n", '*' => ''],
      'files' => [],
      'expected' => '2.7.7',
    ];
    yield 'node' => [
      'field' => 'node',
      'shell_exec_map' => ['node --version' => "v20.18.0\n", '*' => ''],
      'files' => [],
      'expected' => '20.18.0',
    ];
    yield 'npm' => [
      'field' => 'npm',
      'shell_exec_map' => ['npm --version' => "10.8.2\n", '*' => ''],
      'files' => [],
      'expected' => '10.8.2',
    ];
    yield 'drush version (binary present)' => [
      'field' => 'drush',
      'shell_exec_map' => ['--version' => "Drush Commandline Tool 13.3.0\n", '*' => ''],
      'files' => ['build/vendor/bin/drush' => TRUE],
      'expected' => '13.3.0',
    ];
    yield 'drush version (binary absent)' => [
      'field' => 'drush',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => '-',
    ];
    yield 'drupal-version via drush' => [
      'field' => 'drupal-version',
      'shell_exec_map' => ['status --field=drupal-version' => "11.3.11\n", '*' => ''],
      'files' => ['build/vendor/bin/drush' => TRUE],
      'expected' => '11.3.11',
    ];
    yield 'drupal-version no drush' => [
      'field' => 'drupal-version',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => '-',
    ];
  }

  /**
   * @param array<string, string> $env
   *   The environment variables to set.
   */
  #[DataProvider('dataProviderInfoFieldFromDotenv')]
  public function testInfoFieldFromDotenv(string $field, array $env, string $expected): void {
    self::envSetMultiple($env);

    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: ['.env' => TRUE],
      file_contents: ['.env' => "DRUPAL_PROFILE=minimal\nWEBDRIVER_BACKEND=selenium\nWEBDRIVER_HOST=10.0.0.5\n"],
    );

    $output = $this->runInfoField($field, 0);

    $this->assertSame($expected . PHP_EOL, $output);
  }

  public static function dataProviderInfoFieldFromDotenv(): \Iterator {
    yield 'profile from .env' => ['drupal-profile', [], 'minimal'];
    yield 'profile from the environment over .env' => ['drupal-profile', ['DRUPAL_PROFILE' => 'testing'], 'testing'];
    yield 'backend from .env' => ['webdriver-backend', [], 'selenium'];
    yield 'backend from the environment over .env' => ['webdriver-backend', ['WEBDRIVER_BACKEND' => 'chromedriver'], 'chromedriver'];
    yield 'host from .env' => ['webdriver-host', [], '10.0.0.5'];
    yield 'host from the environment over .env' => ['webdriver-host', ['WEBDRIVER_HOST' => 'host.docker.internal'], 'host.docker.internal'];
  }

  public function testInfoFieldModeUnknownFieldPrintsDashAndExits1(): void {
    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: [],
      info_files: [],
    );

    $output = $this->runInfoField('not-a-field', 1);

    $this->assertSame('-' . PHP_EOL, $output);
  }

  public function testInfoFieldModeBypassedForFlagLikeArg(): void {
    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: [],
      info_files: [],
    );

    $output = $this->runCommand('eddy-info', NULL, ['info', '--no-coverage']);

    $this->assertStringContainsString('ENVIRONMENT INFO', $output);
  }

  protected function runInfo(): string {
    return $this->runCommand('eddy-info');
  }

  protected function runInfoField(string $field, int $expected_exit_code): string {
    $this->mockQuit($expected_exit_code);

    return $this->runCommand('eddy-info', $expected_exit_code, ['info', $field]);
  }

}
