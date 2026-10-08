<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use DrevOps\Eddy\Tests\Exceptions\QuitSuccessException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-info' command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class InfoTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';

    // Unset these so each test starts from a known state and sees only the
    // values it sets explicitly via envSet().
    $this->envUnset('WEBSERVER_HOST');
    $this->envUnset('WEBSERVER_PORT');
    $this->envUnset('DRUPAL_PROFILE');
  }

  /**
   * Set up the standard mocks for an info run.
   *
   * @param array<string, string> $shell_exec_map
   *   Map of substring → full command output. The first key whose
   *   substring appears in the requested command wins. A literal
   *   '*' key acts as a catch-all default.
   * @param array<string, bool> $files
   *   Map of file path → existence; used for file_exists, is_file,
   *   and is_dir lookups.
   * @param string[] $info_files
   *   Files returned from glob('*.info.yml').
   * @param string $cwd
   *   Value to return from getcwd().
   * @param array<string, string> $file_contents
   *   Map of file path → contents, used by file_get_contents.
   * @param string|false $command_php_path
   *   Path returned by command_path('php'); FALSE means not found.
   */
  protected function setupInfoMocks(
    array $shell_exec_map,
    array $files = [],
    array $info_files = [],
    string $cwd = '/test/project',
    array $file_contents = [],
    string|false $command_php_path = '/usr/bin/php',
  ): void {
    $this->registerMock('getcwd', 'DrevOps\\Eddy\\DevTools', fn(): string => $cwd);

    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd) use ($shell_exec_map): string {
      foreach ($shell_exec_map as $needle => $output) {
        if ($needle !== '*' && str_contains($cmd, $needle)) {
          return $output;
        }
      }

      return $shell_exec_map['*'] ?? '';
    });

    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $path): bool => $files[$path] ?? FALSE);
    $this->registerMock('is_file', 'DrevOps\\Eddy\\DevTools', fn(string $path): bool => $files[$path] ?? FALSE);
    $this->registerMock('is_dir', 'DrevOps\\Eddy\\DevTools', fn(string $path): bool => $files[$path] ?? FALSE);
    $this->registerMock('glob', 'DrevOps\\Eddy\\DevTools', fn(): array => $info_files);
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\DevTools', fn(string $file): string => $file_contents[$file] ?? '');

    $this->registerMock('exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd, ?array &$output = NULL, ?int &$code = NULL) use ($command_php_path): bool {
      $output ??= [];
      if ($command_php_path !== FALSE && str_contains($cmd, 'command -v php')) {
        $output[] = $command_php_path;
        $code = 0;

        return TRUE;
      }
      $code = 1;

      return FALSE;
    });
  }

  public function testInfoFullyPopulatedEnvironment(): void {
    $this->envSet('WEBSERVER_HOST', 'example.com');
    $this->envSet('WEBSERVER_PORT', '9001');
    $this->envSet('DRUPAL_PROFILE', 'minimal');

    $cwd = '/test/project';
    $drush_bin = 'build/vendor/bin/drush';

    $this->setupInfoMocks(
      shell_exec_map: [
        'php -v' => "PHP 8.3.14 (cli) (built: ...)\n",
        'composer --version' => "Composer version 2.7.7 2024-06-10 22:11:12\n",
        'node --version' => "v20.18.0\n",
        'npm --version' => "10.8.2\n",
        '--version' => "Drush Commandline Tool 13.3.0\n",
        'status --field=drupal-version' => "11.3.11\n",
        'lsof -ti' => "php -S example.com:9001\n",
      ],
      files: [
        $drush_bin => TRUE,
        $cwd . '/build' => TRUE,
      ],
      info_files: ['your_extension.info.yml'],
      cwd: $cwd,
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
    $cwd = '/test/project';

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
      cwd: $cwd,
      file_contents: ['.env' => "WEBSERVER_PORT=8123\n"],
    );

    $output = $this->runInfo();

    $this->assertStringContainsString('Webserver host:     localhost (default)', $output);
    $this->assertStringContainsString('Webserver port:     8123 (.env)', $output);
    $this->assertStringContainsString('Site URL:           http://localhost:8123', $output);
  }

  public function testInfoSiteUrlPrefersTunnelUrl(): void {
    $cwd = '/test/project';
    $tunnel_url = 'https://random-words.trycloudflare.com';

    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: ['.env' => TRUE],
      info_files: [],
      cwd: $cwd,
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
      cwd: '/test/project',
      file_contents: ['.env' => 'TUNNEL_URL=' . $tunnel_url . "\n"],
    );

    $output = $this->runInfoField('site-url', 0);

    $this->assertSame($tunnel_url . PHP_EOL, $output);
  }

  public function testInfoFallsBackToDefaultsWhenNothingResolves(): void {
    $cwd = '/test/project';

    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: [],
      info_files: [],
      cwd: $cwd,
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

  public function testInfoDrushMissingSuppressesPhpPathSuffixOnlyWhenPhpAlsoMissing(): void {
    // PHP is detectable but command_path('php') returns FALSE - the path
    // suffix is omitted.
    $cwd = '/test/project';

    $this->setupInfoMocks(
      shell_exec_map: [
        'php -v' => "PHP 8.3.14\n",
        '*' => '',
      ],
      files: [],
      info_files: [],
      cwd: $cwd,
      command_php_path: FALSE,
    );

    $output = $this->runInfo();

    $this->assertStringContainsString("PHP version:        8.3.14\n", $output);
    $this->assertStringNotContainsString('PHP version:        8.3.14 (', $output);
  }

  #[DataProvider('dataProviderInfoXdebugStateDetection')]
  public function testInfoXdebugStateDetection(string $ps_output, string $expected_state): void {
    $cwd = '/test/project';

    $this->setupInfoMocks(
      shell_exec_map: [
        'lsof -ti' => $ps_output,
        '*' => '',
      ],
      files: [],
      info_files: [],
      cwd: $cwd,
    );

    $output = $this->runInfo();

    $this->assertStringContainsString('XDebug:             ' . $expected_state, $output);
  }

  public static function dataProviderInfoXdebugStateDetection(): \Iterator {
    yield 'no server listening' => ['ps_output' => '', 'expected_state' => '-'];
    yield 'server running without xdebug' => [
      'ps_output' => "php -S localhost:8000\n",
      'expected_state' => 'disabled',
    ];
    yield 'server running with xdebug' => [
      'ps_output' => "php -d xdebug.mode=debug -d xdebug.start_with_request=yes -S localhost:8000\n",
      'expected_state' => 'enabled',
    ];
  }

  public function testInfoDrushDrupalVersionFallbackWhenStatusReturnsNothing(): void {
    // The drush binary exists but `drush status --field=drupal-version`
    // returns empty, for example before the site is installed.
    $cwd = '/test/project';
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
      cwd: $cwd,
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
      cwd: '/test/project',
    );

    $output = $this->runInfoField($field, 0);

    $this->assertSame($expected . PHP_EOL, $output);
  }

  public static function dataProviderInfoFieldModeKnownFields(): \Iterator {
    yield 'xdebug enabled' => [
      'field' => 'xdebug',
      'shell_exec_map' => ['lsof -ti' => "php -d xdebug.mode=debug -S localhost:8000\n", '*' => ''],
      'files' => [],
      'expected' => 'enabled',
    ];
    yield 'xdebug disabled' => [
      'field' => 'xdebug',
      'shell_exec_map' => ['lsof -ti' => "php -S localhost:8000\n", '*' => ''],
      'files' => [],
      'expected' => 'disabled',
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
    yield 'drupal-profile default' => [
      'field' => 'drupal-profile',
      'shell_exec_map' => ['*' => ''],
      'files' => [],
      'expected' => 'standard',
    ];
    yield 'build-dir present' => [
      'field' => 'build-dir',
      'shell_exec_map' => ['*' => ''],
      'files' => ['/test/project/build' => TRUE],
      'expected' => '/test/project/build',
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

  public function testInfoFieldModeUnknownFieldPrintsDashAndExits1(): void {
    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: [],
      info_files: [],
      cwd: '/test/project',
    );

    $output = $this->runInfoField('not-a-field', 1);

    $this->assertSame('-' . PHP_EOL, $output);
  }

  public function testInfoFieldModeBypassedForFlagLikeArg(): void {
    $this->setupInfoMocks(
      shell_exec_map: ['*' => ''],
      files: [],
      info_files: [],
      cwd: '/test/project',
    );

    $argv = ['info', '--no-coverage'];
    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-info';
    $output = ob_get_clean();
    $this->assertIsString($output);

    $this->assertStringContainsString('ENVIRONMENT INFO', $output);
  }

  protected function runInfo(): string {
    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-info';

    return (string) ob_get_clean();
  }

  protected function runInfoField(string $field, int $expected_exit_code): string {
    $this->mockQuit($expected_exit_code);

    // The included info script inherits this method's scope, so it reads the
    // field argument from $argv.
    $argv = ['info', $field];

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-info';
      $this->fail('Expected info to call quit() in field mode.');
    }
    catch (QuitSuccessException | QuitErrorException $e) {
      $this->assertSame($expected_exit_code, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
    }

    return $output;
  }

}
