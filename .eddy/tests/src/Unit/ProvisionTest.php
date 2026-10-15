<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\site_db_file;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-provision' command.
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class ProvisionTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    self::envUnset('CLOUDFLARE_TUNNEL');
  }

  protected static function drushPrefix(string $cwd): string {
    return 'build/vendor/bin/drush -r ' . escapeshellarg($cwd . '/build/web') . ' -y ';
  }

  /**
   * @return array<int, string>
   *   The '[TASK]' and '[ OK ]' lines of the output, in order.
   */
  protected static function statusLines(string $output): array {
    preg_match_all('/^\[(?:TASK| OK )\] .*$/m', $output, $matches);

    return $matches[0];
  }

  /**
   * @return array<int, string>
   *   The lines that name a command wrapper's command list, in order.
   */
  protected static function hintLines(string $output): array {
    preg_match_all('/^Run `.*$/m', $output, $matches);

    return $matches[0];
  }

  #[DataProvider('dataProviderProvisionSuccess')]
  public function testProvisionSuccess(array $env, string $extension_type, string $db_status, array $suggested, ?array $locked_types, array $expected_enabled, bool $has_ahoy, bool $has_makefile): void {
    foreach ($env as $name => $value) {
      self::envSet($name, $value);
    }

    $expected_host = $env['WEBSERVER_HOST'] ?? 'localhost';
    $expected_port = $env['WEBSERVER_PORT'] ?? '8000';
    $drupal_profile = $env['DRUPAL_PROFILE'] ?? 'standard';

    $extension_name = 'test_extension';
    $info_content = $extension_type === 'theme' ? "name: Test\ntype: theme\n" : "name: Test\ntype: module\n";
    $cwd = self::$tmp . '/provision_' . uniqid();
    File::mkdir($cwd . '/build/web', 0755);

    $this->mockFunction('getcwd', fn(): string => $cwd);

    $this->mockFunction('glob', fn(): array => [$extension_name . '.info.yml']);

    $composer_json = json_encode(['suggest' => $suggested], JSON_THROW_ON_ERROR);
    $composer_lock = $locked_types === NULL ? '' : json_encode(['packages' => array_map(static fn(string $name, string $type): array => ['name' => $name, 'type' => $type], array_keys($locked_types), $locked_types)], JSON_THROW_ON_ERROR);
    $file_get_contents_calls = 0;
    $this->mockFunction('file_get_contents', function (string $file) use (&$file_get_contents_calls, $info_content, $composer_json, $composer_lock): string {
      $file_get_contents_calls++;
      if (str_ends_with($file, '.info.yml')) {
        return $info_content;
      }
      if ($file === 'composer.json') {
        return $composer_json;
      }
      if ($file === 'build/composer.lock') {
        return $composer_lock;
      }
      // The cache pre-warm fetches the site over HTTP.
      if (str_starts_with($file, 'http://')) {
        return '<html></html>';
      }
      return '';
    });

    $this->mockFunction('file_exists', function (string $file) use ($has_ahoy, $has_makefile): bool {
      if ($file === '.ahoy.yml') {
        return $has_ahoy;
      }
      if ($file === 'Makefile') {
        return $has_makefile;
      }
      return FALSE;
    });

    $prefix = self::drushPrefix($cwd);
    $passthru_responses = [];

    $passthru_responses[] = ['cmd' => $prefix . 'status --field=db-status', 'output' => $db_status];

    if (trim($db_status) === 'Connected') {
      $passthru_responses[] = ['cmd' => $prefix . 'sql:drop -y'];
    }

    $db_file = site_db_file($extension_name);
    $passthru_responses[] = ['cmd' => $prefix . sprintf('site-install %s -y --db-url=%s --account-name=admin install_configure_form.enable_update_status_module=NULL install_configure_form.enable_update_status_emails=NULL', escapeshellarg($drupal_profile), escapeshellarg('sqlite://localhost/' . $db_file))];

    $passthru_responses[] = ['cmd' => $prefix . 'status'];

    if ($extension_type === 'theme') {
      $passthru_responses[] = ['cmd' => $prefix . 'theme:enable ' . escapeshellarg($extension_name)];
    }
    else {
      $passthru_responses[] = ['cmd' => $prefix . 'pm:enable ' . escapeshellarg($extension_name)];
    }

    $passthru_responses[] = ['cmd' => $prefix . 'cr'];

    foreach ($expected_enabled as $name => $drush_command) {
      $passthru_responses[] = ['cmd' => $prefix . $drush_command . ' ' . escapeshellarg($name)];
    }

    $site_url = sprintf('http://%s:%s', $expected_host, $expected_port);
    $passthru_responses[] = ['cmd' => $prefix . 'status --field=drush-version', 'output' => '13.8.0'];
    $passthru_responses[] = ['cmd' => $prefix . sprintf('uli -l %s --no-browser', escapeshellarg($site_url)), 'output' => $site_url . '/user/reset/1/abc/login'];

    $this->mockPassthruMultiple($passthru_responses);

    ob_start();
    require self::toolingDir() . '/src/eddy-provision';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('PROVISION', $output);
    $this->assertSame([
      '[TASK] Installing Drupal into SQLite database ' . $db_file . '.',
      '[ OK ] Drupal installed.',
      '[TASK] Enabling extension ' . $extension_name . '.',
      '[ OK ] Extension enabled.',
      '[TASK] Clearing caches.',
      '[ OK ] Caches cleared.',
      '[TASK] Enabling suggested extensions, if any.',
      '[ OK ] Suggested extensions enabled.',
      '[TASK] Pre-warming caches.',
      '[ OK ] Caches pre-warmed.',
      '[TASK] Linking browser test output into .logs/browser_output.',
      '[ OK ] Browser test output linked.',
    ], self::statusLines($output), 'Every step must report its outcome before the next one starts.');
    $this->assertStringContainsString('PROVISION COMPLETE', $output);
    $this->assertStringContainsString('http://' . $expected_host . ':' . $expected_port, $output);
    $this->assertStringNotContainsString('tunnel settings', $output);

    $browser_output_link = $cwd . '/build/web/sites/simpletest/browser_output';
    $this->assertTrue(is_link($browser_output_link));
    $this->assertSame($cwd . '/.logs/browser_output', readlink($browser_output_link));

    $expected_hints = [];

    if ($has_ahoy) {
      $expected_hints[] = 'Run `ahoy` to see available commands.';
    }

    if ($has_makefile) {
      $expected_hints[] = 'Run `make help` to see available commands.';
    }

    $this->assertSame($expected_hints, self::hintLines($output));
  }

  public static function dataProviderProvisionSuccess(): \Iterator {
    yield 'module, fresh install, no suggestions, no helpers' => [
      'env' => [],
      'extension_type' => 'module',
      'db_status' => '',
      'suggested' => [],
      'locked_types' => NULL,
      'expected_enabled' => [],
      'has_ahoy' => FALSE,
      'has_makefile' => FALSE,
    ];
    yield 'module, connected DB, with suggestions, ahoy and make' => [
      'env' => [],
      'extension_type' => 'module',
      'db_status' => 'Connected',
      'suggested' => ['drupal/token' => 'Provides token support', 'drupal/pathauto' => 'Provides path aliases'],
      'locked_types' => ['drupal/token' => 'drupal-module', 'drupal/pathauto' => 'drupal-module'],
      'expected_enabled' => ['token' => 'pm:enable', 'pathauto' => 'pm:enable'],
      'has_ahoy' => TRUE,
      'has_makefile' => TRUE,
    ];
    yield 'theme, fresh install, no suggestions, ahoy only' => [
      'env' => [],
      'extension_type' => 'theme',
      'db_status' => '',
      'suggested' => [],
      'locked_types' => NULL,
      'expected_enabled' => [],
      'has_ahoy' => TRUE,
      'has_makefile' => FALSE,
    ];
    yield 'module, custom profile and port' => [
      'env' => ['DRUPAL_PROFILE' => 'minimal', 'WEBSERVER_PORT' => '9000', 'WEBSERVER_HOST' => '0.0.0.0'],
      'extension_type' => 'module',
      'db_status' => '',
      'suggested' => [],
      'locked_types' => NULL,
      'expected_enabled' => [],
      'has_ahoy' => FALSE,
      'has_makefile' => TRUE,
    ];
    yield 'module with non-drupal suggestions filtered out' => [
      'env' => [],
      'extension_type' => 'module',
      'db_status' => '',
      'suggested' => ['drupal/token' => 'Token support', 'ext-json' => 'JSON extension', 'drupal/views_bulk_operations' => 'VBO'],
      'locked_types' => ['drupal/token' => 'drupal-module', 'drupal/views_bulk_operations' => 'drupal-module'],
      'expected_enabled' => ['token' => 'pm:enable', 'views_bulk_operations' => 'pm:enable'],
      'has_ahoy' => FALSE,
      'has_makefile' => FALSE,
    ];
    yield 'module with drupal suggestion containing colon' => [
      'env' => [],
      'extension_type' => 'module',
      'db_status' => '',
      'suggested' => ['drupal/token:^2.0' => 'Token support with version constraint'],
      'locked_types' => ['drupal/token' => 'drupal-module'],
      'expected_enabled' => ['token' => 'pm:enable'],
      'has_ahoy' => FALSE,
      'has_makefile' => FALSE,
    ];
    yield 'module with a suggested theme and module' => [
      'env' => [],
      'extension_type' => 'module',
      'db_status' => '',
      'suggested' => ['drupal/gin' => 'Admin theme', 'drupal/token' => 'Token support'],
      'locked_types' => ['drupal/gin' => 'drupal-theme', 'drupal/gin_toolbar' => 'drupal-module', 'drupal/token' => 'drupal-module'],
      'expected_enabled' => ['gin' => 'theme:enable', 'token' => 'pm:enable'],
      'has_ahoy' => FALSE,
      'has_makefile' => FALSE,
    ];
    yield 'theme with a suggested theme' => [
      'env' => [],
      'extension_type' => 'theme',
      'db_status' => '',
      'suggested' => ['drupal/bootstrap5' => 'Base theme'],
      'locked_types' => ['drupal/bootstrap5' => 'drupal-theme'],
      'expected_enabled' => ['bootstrap5' => 'theme:enable'],
      'has_ahoy' => FALSE,
      'has_makefile' => FALSE,
    ];
    yield 'module with a suggested theme containing colon' => [
      'env' => [],
      'extension_type' => 'module',
      'db_status' => '',
      'suggested' => ['drupal/bootstrap5:^4' => 'Theme with version constraint'],
      'locked_types' => ['drupal/bootstrap5' => 'drupal-theme'],
      'expected_enabled' => ['bootstrap5' => 'theme:enable'],
      'has_ahoy' => FALSE,
      'has_makefile' => FALSE,
    ];
    yield 'module with a suggested custom theme' => [
      'env' => [],
      'extension_type' => 'module',
      'db_status' => '',
      'suggested' => ['drupal/custom_theme' => 'Custom theme from a VCS repository'],
      'locked_types' => ['drupal/custom_theme' => 'drupal-custom-theme'],
      'expected_enabled' => ['custom_theme' => 'theme:enable'],
      'has_ahoy' => FALSE,
      'has_makefile' => FALSE,
    ];
    yield 'module with a suggestion missing from the lock file' => [
      'env' => [],
      'extension_type' => 'module',
      'db_status' => '',
      'suggested' => ['drupal/token' => 'Token support'],
      'locked_types' => ['drupal/pathauto' => 'drupal-module'],
      'expected_enabled' => ['token' => 'pm:enable'],
      'has_ahoy' => FALSE,
      'has_makefile' => FALSE,
    ];
    yield 'module with suggestions and no lock file' => [
      'env' => [],
      'extension_type' => 'module',
      'db_status' => '',
      'suggested' => ['drupal/token' => 'Token support', 'drupal/gin' => 'Admin theme'],
      'locked_types' => NULL,
      'expected_enabled' => ['token' => 'pm:enable', 'gin' => 'pm:enable'],
      'has_ahoy' => FALSE,
      'has_makefile' => FALSE,
    ];
  }

  public function testProvisionReadsPortFromDotenvWhenEnvUnset(): void {
    $extension_name = 'test_extension';
    $info_content = "name: Test\ntype: module\n";
    $cwd = '/test/project';
    $expected_host = 'localhost';
    $expected_port = '8123';

    $this->mockFunction('getcwd', fn(): string => $cwd);
    $this->mockFunction('glob', fn(): array => [$extension_name . '.info.yml']);

    $this->mockFunction('file_get_contents', function (string $file) use ($info_content): string {
      if (str_ends_with($file, '.info.yml')) {
        return $info_content;
      }
      if ($file === '.env') {
        return "WEBSERVER_PORT=8123\n";
      }
      if ($file === 'composer.json') {
        return json_encode(['suggest' => []], JSON_THROW_ON_ERROR);
      }
      if (str_starts_with($file, 'http://')) {
        return '<html></html>';
      }

      return '';
    });

    $this->mockFunction('file_exists', fn(string $file): bool => $file === '.env');

    $prefix = self::drushPrefix($cwd);
    $db_file = site_db_file($extension_name);
    $site_url = sprintf('http://%s:%s', $expected_host, $expected_port);
    $this->mockPassthruMultiple([
      ['cmd' => $prefix . 'status --field=db-status', 'output' => ''],
      ['cmd' => $prefix . sprintf('site-install %s -y --db-url=%s --account-name=admin install_configure_form.enable_update_status_module=NULL install_configure_form.enable_update_status_emails=NULL', escapeshellarg('standard'), escapeshellarg('sqlite://localhost/' . $db_file))],
      ['cmd' => $prefix . 'status'],
      ['cmd' => $prefix . 'pm:enable ' . escapeshellarg($extension_name)],
      ['cmd' => $prefix . 'cr'],
      ['cmd' => $prefix . 'status --field=drush-version', 'output' => '13.8.0'],
      ['cmd' => $prefix . sprintf('uli -l %s --no-browser', escapeshellarg($site_url)), 'output' => $site_url . '/user/reset/1/abc/login'],
    ]);

    ob_start();
    require self::toolingDir() . '/src/eddy-provision';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('http://' . $expected_host . ':' . $expected_port, $output);
  }

  #[DataProvider('dataProviderProvisionLoginLinkOptions')]
  public function testProvisionLoginLinkOptions(string $drush_version, string $expected_options): void {
    $extension_name = 'test_extension';
    $cwd = '/test/project';
    $site_url = 'http://localhost:8000';

    $this->mockFunction('getcwd', fn(): string => $cwd);
    $this->mockFunction('glob', fn(): array => [$extension_name . '.info.yml']);

    $this->mockFunction('file_get_contents', function (string $file): string {
      if (str_ends_with($file, '.info.yml')) {
        return "name: Test\ntype: module\n";
      }
      if ($file === 'composer.json') {
        return json_encode(['suggest' => []], JSON_THROW_ON_ERROR);
      }
      if (str_starts_with($file, 'http://')) {
        return '<html></html>';
      }

      return '';
    });

    $this->mockFunction('file_exists', fn(): bool => FALSE);

    $prefix = self::drushPrefix($cwd);
    $db_file = site_db_file($extension_name);
    $this->mockPassthruMultiple([
      ['cmd' => $prefix . 'status --field=db-status', 'output' => ''],
      ['cmd' => $prefix . sprintf('site-install %s -y --db-url=%s --account-name=admin install_configure_form.enable_update_status_module=NULL install_configure_form.enable_update_status_emails=NULL', escapeshellarg('standard'), escapeshellarg('sqlite://localhost/' . $db_file))],
      ['cmd' => $prefix . 'status'],
      ['cmd' => $prefix . 'pm:enable ' . escapeshellarg($extension_name)],
      ['cmd' => $prefix . 'cr'],
      ['cmd' => $prefix . 'status --field=drush-version', 'output' => $drush_version . "\n"],
      ['cmd' => $prefix . sprintf('uli -l %s', escapeshellarg($site_url)) . $expected_options, 'output' => $site_url . '/user/reset/1/abc/login'],
    ]);

    ob_start();
    require self::toolingDir() . '/src/eddy-provision';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('One-time login link: ' . $site_url . '/user/reset/1/abc/login', $output);
  }

  public static function dataProviderProvisionLoginLinkOptions(): \Iterator {
    yield 'Drush 12' => ['12.5.3', ' --no-browser'];
    yield 'Drush 13' => ['13.8.0', ' --no-browser'];
    yield 'Drush 14' => ['14.0.0', ''];
    yield 'Drush 14 development branch' => ['14.x-dev', ''];
  }

  public function testProvisionDisplaysTunnelUrlWhenSet(): void {
    $extension_name = 'test_extension';
    $info_content = "name: Test\ntype: module\n";
    $cwd = '/test/project';
    $tunnel_url = 'https://abc.trycloudflare.com';

    $this->mockFunction('getcwd', fn(): string => $cwd);
    $this->mockFunction('glob', fn(): array => [$extension_name . '.info.yml']);

    $this->mockFunction('file_get_contents', function (string $file) use ($info_content, $tunnel_url): string {
      if (str_ends_with($file, '.info.yml')) {
        return $info_content;
      }
      if ($file === '.env') {
        return "WEBSERVER_PORT=8000\nTUNNEL_URL=" . $tunnel_url . "\n";
      }
      if ($file === 'composer.json') {
        return json_encode(['suggest' => []], JSON_THROW_ON_ERROR);
      }
      if (str_starts_with($file, 'http://')) {
        return '<html></html>';
      }

      return '';
    });

    $this->mockFunction('file_exists', fn(string $file): bool => $file === '.env');

    $prefix = self::drushPrefix($cwd);
    $db_file = site_db_file($extension_name);
    // The pre-warm still hits the local host:port, but the login link and the
    // completion banner report the public tunnel URL.
    $this->mockPassthruMultiple([
      ['cmd' => $prefix . 'status --field=db-status', 'output' => ''],
      ['cmd' => $prefix . sprintf('site-install %s -y --db-url=%s --account-name=admin install_configure_form.enable_update_status_module=NULL install_configure_form.enable_update_status_emails=NULL', escapeshellarg('standard'), escapeshellarg('sqlite://localhost/' . $db_file))],
      ['cmd' => $prefix . 'status'],
      ['cmd' => $prefix . 'pm:enable ' . escapeshellarg($extension_name)],
      ['cmd' => $prefix . 'cr'],
      ['cmd' => $prefix . 'status --field=drush-version', 'output' => '13.8.0'],
      ['cmd' => $prefix . sprintf('uli -l %s --no-browser', escapeshellarg($tunnel_url)), 'output' => $tunnel_url . '/user/reset/1/abc/login'],
    ]);

    ob_start();
    require self::toolingDir() . '/src/eddy-provision';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('Site URL:            ' . $tunnel_url, $output);
    $this->assertStringNotContainsString('Site URL:            http://', $output);
  }

  public function testProvisionAddsTunnelSettingsWhenEnabled(): void {
    self::envSet('CLOUDFLARE_TUNNEL', '1');

    $extension_name = 'test_extension';
    $cwd = self::$tmp . '/provision_tunnel_' . uniqid();
    $settings_file = $cwd . '/build/web/sites/default/settings.php';
    File::dump($settings_file, "<?php\n");
    chdir($cwd);

    $this->mockFunction('getcwd', fn(): string => $cwd);
    $this->mockFunction('glob', fn(): array => [$extension_name . '.info.yml']);

    $this->mockFunction('file_get_contents', function (string $file): string {
      if (str_ends_with($file, '.info.yml')) {
        return "name: Test\ntype: module\n";
      }
      if ($file === 'composer.json') {
        return json_encode(['suggest' => []], JSON_THROW_ON_ERROR);
      }
      if (str_starts_with($file, 'http://')) {
        return '<html></html>';
      }

      return (string) \file_get_contents($file);
    });

    $this->mockFunction('file_exists', fn(): bool => FALSE);

    $prefix = self::drushPrefix($cwd);
    $db_file = site_db_file($extension_name);
    $site_url = 'http://localhost:8000';
    $this->mockPassthruMultiple([
      ['cmd' => $prefix . 'status --field=db-status', 'output' => ''],
      ['cmd' => $prefix . sprintf('site-install %s -y --db-url=%s --account-name=admin install_configure_form.enable_update_status_module=NULL install_configure_form.enable_update_status_emails=NULL', escapeshellarg('standard'), escapeshellarg('sqlite://localhost/' . $db_file))],
      ['cmd' => $prefix . 'status'],
      ['cmd' => $prefix . 'pm:enable ' . escapeshellarg($extension_name)],
      ['cmd' => $prefix . 'cr'],
      ['cmd' => $prefix . 'status --field=drush-version', 'output' => '13.8.0'],
      ['cmd' => $prefix . sprintf('uli -l %s --no-browser', escapeshellarg($site_url)), 'output' => $site_url . '/user/reset/1/abc/login'],
    ]);

    ob_start();
    require self::toolingDir() . '/src/eddy-provision';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('Adding the tunnel settings to build/web/sites/default/settings.php.', $output);
    $this->assertStringContainsString('Tunnel settings added.', $output);
    $this->assertStringContainsString("# Cloudflare quick tunnel settings.\n\$settings['reverse_proxy'] = TRUE;", File::read($settings_file));
  }

}
