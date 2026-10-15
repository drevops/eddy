<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-assemble' command.
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class AssembleTest extends UnitTestCase {

  /**
   * Versions listed for 'drupal/recommended-project'.
   */
  protected const array RELEASES = ['12.0.x-dev', '12.0.0-beta1', '12.0.0-alpha1', '11.x-dev', '11.5.0-beta1', '11.4.x-dev', '11.4.8', '11.4.0', '11.1.x-dev', '11.1.10', '11.1.0', '10.6.x-dev', '10.6.18', '10.6.0'];

  /**
   * @var array<int, string>
   */
  protected array $capturedBuildComposerJson = [];

  /**
   * Names of the items symlinked into the extension directory.
   *
   * @var array<int, string>
   */
  protected array $symlinkedItems = [];

  /**
   * Assignments passed to putenv(), in call order.
   *
   * @var array<int, string>
   */
  protected array $putenvCalls = [];

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  /**
   * Build mocks and expected passthru sequence for an assemble run.
   *
   * @param array $config
   *   Configuration with keys: extension_name, extension_type, drupal_version,
   *   drupal_release, releases, releases_result_code, has_build_dir,
   *   has_patches, github_token, suggestions, extension_require,
   *   extension_require_dev, extension_extra, extension_config,
   *   dev_composer_json,
   *   has_deprecations_disabled, has_package_lock, has_skip_npm_build,
   *   has_nvmrc, has_nvm, has_node_modules, tool_files,
   *   has_version_specific_phpunit, has_polyfill_bootstrap,
   *   create_project_result_code, installed_packages, php_requirement,
   *   expect_php_failure. A NULL drupal_release expects the release
   *   resolution to fail, and a NULL php_requirement makes the core lookup
   *   fail.
   *
   * @return array
   *   The configuration with defaults applied, plus nvm_dir when has_nvmrc is
   *   set.
   */
  protected function setupAssembleMocks(array $config): array {
    $config += [
      'extension_name' => 'test_extension',
      'extension_type' => 'module',
      'drupal_version' => '11',
      'drupal_release' => '11.4.8',
      'releases' => self::RELEASES,
      'releases_result_code' => 0,
      'php_requirement' => '>=8.1',
      'expect_php_failure' => FALSE,
      'has_build_dir' => FALSE,
      'has_patches' => FALSE,
      'github_token' => '',
      'suggestions' => [],
      'extension_require' => [],
      'extension_require_dev' => [],
      'extension_extra' => [],
      'extension_config' => [],
      'dev_composer_json' => ['require-dev' => ['drupal/coder' => '^8']],
      'has_deprecations_disabled' => FALSE,
      'has_package_lock' => FALSE,
      'has_skip_npm_build' => FALSE,
      'has_nvmrc' => FALSE,
      'has_nvm' => FALSE,
      'has_node_modules' => FALSE,
      'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      'has_version_specific_phpunit' => FALSE,
      'has_polyfill_bootstrap' => FALSE,
      'create_project_result_code' => 0,
      'installed_packages' => [],
    ];

    $cwd = '/test/project';
    $drupal_version_major = explode('.', explode('@', (string) $config['drupal_version'])[0])[0];

    $composer_json = ['name' => 'drupal/' . $config['extension_name']];
    if ($config['extension_require'] !== []) {
      $composer_json['require'] = $config['extension_require'];
    }
    if ($config['extension_require_dev'] !== []) {
      $composer_json['require-dev'] = $config['extension_require_dev'];
    }
    if ($config['suggestions'] !== []) {
      $composer_json['suggest'] = $config['suggestions'];
    }
    if ($config['extension_extra'] !== []) {
      $composer_json['extra'] = $config['extension_extra'];
    }
    if ($config['extension_config'] !== []) {
      $composer_json['config'] = $config['extension_config'];
    }
    $composer_json_str = json_encode($composer_json, JSON_THROW_ON_ERROR);

    // The mocked build/composer.json includes the extension's require and
    // require-dev, as the real assemble flow merges them in.
    $build_composer_json = json_encode([
      'repositories' => [
        ['type' => 'composer', 'url' => 'https://packages.drupal.org/8'],
      ],
      'require' => array_merge(['drupal/core-recommended' => '^11', 'drupal/core-composer-scaffold' => '^11'], $config['extension_require']),
      'require-dev' => array_merge(['drupal/core-dev' => '^11'], $config['extension_require_dev']),
      'minimum-stability' => 'stable',
      'prefer-stable' => TRUE,
    ], JSON_THROW_ON_ERROR);

    $dev_composer_json = json_encode($config['dev_composer_json'], JSON_THROW_ON_ERROR);

    $installed_packages = [];
    foreach ($config['installed_packages'] as $name => $version) {
      $installed_packages[] = ['name' => $name, 'version' => $version];
    }
    $build_composer_lock = json_encode(['packages' => $installed_packages], JSON_THROW_ON_ERROR);

    $this->registerMock('getcwd', 'DrevOps\\Eddy\\Tooling', fn(): string => $cwd);

    $this->registerMock('exec', 'DrevOps\\Eddy\\Tooling', function (string $command, ?array &$output = NULL, ?int &$result_code = NULL) use ($config): string {
      $output ??= [];
      $result_code = 0;
      if (str_contains($command, 'command -v composer')) {
        $output[] = '/usr/bin/composer';
      }
      if ($command === self::composerShowCommand('drupal/recommended-project')) {
        $output = explode("\n", json_encode(['name' => 'drupal/recommended-project', 'versions' => $config['releases']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $result_code = $config['releases_result_code'];
      }
      if ($command === self::composerShowCommand('drupal/core', (string) $config['drupal_release'])) {
        $output = is_string($config['php_requirement']) ? explode("\n", json_encode(['name' => 'drupal/core', 'requires' => ['php' => $config['php_requirement']]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) : [];
        $result_code = is_string($config['php_requirement']) ? 0 : 1;
      }
      return '';
    });

    $this->registerMock('glob', 'DrevOps\\Eddy\\Tooling', function (string $pattern) use ($config, $cwd): array {
      if ($pattern === '*.info.yml') {
        return [$config['extension_name'] . '.info.yml'];
      }
      if ($pattern === $cwd . '/*') {
        return [$cwd . '/src', $cwd . '/composer.json', $cwd . '/vendor', $cwd . '/build'];
      }
      return [];
    });

    $all_tool_files = ['.eslintignore', '.eslintrc.json', '.prettierignore', '.prettierrc.json', '.stylelintrc.js', '.twig-cs-fixer.php', 'package-lock.json', 'package.json', 'phpcs.xml', 'phpstan.neon', 'phpmd.xml', 'phpunit.xml', 'rector.php'];
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\Tooling', function (string $file) use ($config, $all_tool_files, $drupal_version_major) {
      if ($file === 'composer.json') {
        return TRUE;
      }
      if (str_ends_with($file, '.info.yml')) {
        return TRUE;
      }
      if ($file === 'build/package-lock.json') {
        return $config['has_package_lock'];
      }
      if ($file === '.skip_npm_build') {
        return $config['has_skip_npm_build'];
      }
      if ($file === '.nvmrc') {
        return $config['has_nvmrc'];
      }
      $version_specific = 'phpunit.d' . $drupal_version_major . '.xml';
      if ($file === $version_specific) {
        return $config['has_version_specific_phpunit'];
      }
      if (str_contains($file, 'polyfill-php83/bootstrap.php')) {
        return $config['has_polyfill_bootstrap'];
      }
      if (in_array($file, $all_tool_files, TRUE)) {
        return in_array($file, $config['tool_files'], TRUE);
      }
      return FALSE;
    });

    // The first 'build' check comes from assemble and returns the config
    // value. Later checks come from chmod_recursive and remove_dir and return
    // FALSE to avoid real filesystem iteration.
    $build_dir_checked = FALSE;
    $this->registerMock('is_dir', 'DrevOps\\Eddy\\Tooling', function (string $path) use ($config, &$build_dir_checked) {
      if ($path === 'build') {
        if (!$build_dir_checked) {
          $build_dir_checked = TRUE;
          return $config['has_build_dir'];
        }
        return FALSE;
      }
      if ($path === 'patches') {
        return $config['has_patches'];
      }
      if ($path === 'build/patches') {
        return FALSE;
      }
      if ($path === 'build/web/modules/custom' || $path === 'build/web/themes/custom') {
        return FALSE;
      }
      if ($path === 'build/node_modules') {
        return $config['has_node_modules'];
      }
      return FALSE;
    });

    $this->registerMock('mkdir', 'DrevOps\\Eddy\\Tooling', fn(): true => TRUE);

    $info_content = $config['extension_type'] === 'theme' ? "name: Test\ntype: theme\n" : "name: Test\ntype: module\n";
    $this->registerMock('file_get_contents', 'DrevOps\\Eddy\\Tooling', function (string $file) use ($info_content, $composer_json_str, $build_composer_json, $dev_composer_json, $build_composer_lock) {
      if (str_ends_with($file, '.info.yml')) {
        return $info_content;
      }
      if ($file === 'composer.json') {
        return $composer_json_str;
      }
      // Reads return the last write, so each step builds on the one before it
      // as it does on disk.
      if ($file === 'build/composer.json') {
        return $this->capturedBuildComposerJson === [] ? $build_composer_json : $this->capturedBuildComposerJson[array_key_last($this->capturedBuildComposerJson)];
      }
      if ($file === 'build/composer.lock') {
        return $build_composer_lock;
      }
      if ($file === 'composer.dev.json') {
        return $dev_composer_json;
      }
      if (str_contains($file, 'default.settings.php') || str_contains($file, 'index.php') || str_contains($file, 'bootstrap.php')) {
        return "<?php\n";
      }
      return '';
    });

    $this->capturedBuildComposerJson = [];
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\Tooling', function (string $file, string $content): int {
      if ($file === 'build/composer.json') {
        $this->capturedBuildComposerJson[] = $content;
      }
      return 100;
    });

    $this->registerMock('copy', 'DrevOps\\Eddy\\Tooling', fn(): true => TRUE);

    $this->symlinkedItems = [];
    $this->registerMock('symlink', 'DrevOps\\Eddy\\Tooling', function (string $target, string $link): bool {
      $this->symlinkedItems[] = basename($link);

      return TRUE;
    });

    $this->putenvCalls = [];
    $this->registerMock('putenv', 'DrevOps\\Eddy\\Tooling', function (string $assignment): bool {
      $this->putenvCalls[] = $assignment;

      return TRUE;
    });

    $this->registerMock('unlink', 'DrevOps\\Eddy\\Tooling', fn(): true => TRUE);

    // nvm_prefix() checks for 'nvm.sh' on disk, so NVM_DIR names a real
    // directory.
    if ($config['has_nvmrc']) {
      $config['nvm_dir'] = self::$tmp . '/nvm';
      mkdir($config['nvm_dir']);

      if ($config['has_nvm']) {
        file_put_contents($config['nvm_dir'] . '/nvm.sh', '');
      }

      $this->envSet('NVM_DIR', $config['nvm_dir']);
    }

    $passthru_responses = [];

    $passthru_responses[] = ['cmd' => 'composer validate --ansi --strict'];

    if ($config['drupal_release'] === NULL || $config['expect_php_failure']) {
      $this->mockPassthruMultiple($passthru_responses);

      return $config;
    }

    $passthru_responses[] = ['cmd' => sprintf('composer create-project %s build --no-install --no-interaction', escapeshellarg('drupal/recommended-project:' . $config['drupal_release'])), 'result_code' => $config['create_project_result_code']];

    if ($config['create_project_result_code'] !== 0) {
      $this->mockPassthruMultiple($passthru_responses);

      return $config;
    }

    if ($config['has_patches']) {
      // copy_dir() is a real function using PHP iterators and needs no mock.
      // HelpersCopyDirTest covers it.
    }

    $passthru_responses[] = ['cmd' => 'composer --working-dir=build install'];

    foreach (array_keys($config['suggestions']) as $suggest) {
      if (isset($config['extension_require'][$suggest])) {
        continue;
      }

      if (isset($config['extension_require_dev'][$suggest])) {
        continue;
      }

      $passthru_responses[] = ['cmd' => sprintf('composer --working-dir=build require %s', escapeshellarg((string) $suggest))];
    }

    if ($config['has_package_lock'] && !$config['has_skip_npm_build']) {
      $nvm = $config['has_nvmrc'] && $config['has_nvm'] ? '. "$NVM_DIR/nvm.sh"; { nvm use || nvm install; } && ' : '';
      if (!$config['has_node_modules']) {
        $passthru_responses[] = ['cmd' => $nvm . 'npm --prefix build ci'];
      }
      $passthru_responses[] = ['cmd' => $nvm . 'npm --prefix build run build'];
    }

    $this->mockPassthruMultiple($passthru_responses);

    return $config;
  }

  #[DataProvider('dataProviderAssembleSuccess')]
  public function testAssembleSuccess(array $env, array $config): void {
    foreach ($env as $name => $value) {
      $this->envSet($name, $value);
    }

    // An explicit empty value overrides GITHUB_TOKEN and COMPOSER_AUTH
    // inherited from the environment.
    $this->envSet('GITHUB_TOKEN', $config['github_token'] ?? '');
    $this->envSet('COMPOSER_AUTH', '');

    if ($config['has_deprecations_disabled'] ?? FALSE) {
      $this->envSet('SYMFONY_DEPRECATIONS_HELPER', 'disabled');
    }
    else {
      $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');
    }

    $config = $this->setupAssembleMocks($config);

    // Create a real 'patches' directory for copy_dir(), which uses
    // RecursiveDirectoryIterator and cannot be mocked.
    $temp_dirs = [];
    if ($config['has_patches'] ?? FALSE) {
      File::mkdir('patches');
      $temp_dirs[] = 'patches';
    }

    try {
      ob_start();
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
      $output = ob_get_clean();
    }
    finally {
      foreach ($temp_dirs as $temp_dir) {
        File::rmdirIfEmpty($temp_dir);
      }
    }

    $this->assertIsString($output);
    $this->assertStringContainsString('ASSEMBLE', $output);
    $this->assertStringContainsString('Scaffold is valid', $output);
    $this->assertStringContainsString('Tools are valid', $output);
    $this->assertStringContainsString('Composer configuration is valid', $output);
    $this->assertStringContainsString('Drupal project created', $output);
    $this->assertStringContainsString('Dependencies installed', $output);
    $this->assertStringContainsString("Extension's code symlinked", $output);
    $this->assertSame(['src', 'composer.json'], $this->symlinkedItems, 'The build and vendor directories are not symlinked into the extension.');
    $this->assertStringContainsString('ASSEMBLE COMPLETE', $output);

    $this->assertStringContainsString('Test dependencies configured', $output);
    $this->assertNotEmpty($this->capturedBuildComposerJson, 'Expected at least one write to build/composer.json');
    $all_writes = implode("\n", $this->capturedBuildComposerJson);
    $this->assertStringContainsString('symfony/phpunit-bridge', $all_writes);

    $drupal_version = $env['DRUPAL_VERSION'] ?? '11';
    $this->assertStringContainsString('Resolved Drupal ' . $drupal_version . ' to ' . $config['drupal_release'] . '.', $output);
    $this->assertStringContainsString(sprintf('Drupal %s supports PHP %s.', $config['drupal_release'], self::phpVersion()), $output);
    $this->assertStringContainsString('Creating Drupal ' . $drupal_version . ' project', $output);
    $this->assertStringContainsString('PHP platform set to ' . self::phpVersion() . '.', $output);

    if ($config['has_build_dir']) {
      $this->assertStringContainsString('Removing existing build directory', $output);
    }

    if ($config['has_patches']) {
      $this->assertStringContainsString('Copying patches', $output);
    }

    if (($config['github_token'] ?? '') !== '') {
      $this->assertStringContainsString('GitHub token passed to Composer.', $output);
      $this->assertContains('COMPOSER_AUTH={"github-oauth":{"github.com":"' . $config['github_token'] . '"}}', $this->putenvCalls);
    }

    if ($config['has_deprecations_disabled'] ?? FALSE) {
      $this->assertStringContainsString('Disabling deprecation notices', $output);
    }

    if ($config['has_package_lock'] && !($config['has_skip_npm_build'] ?? FALSE)) {
      $this->assertStringContainsString('Processing front-end dependencies', $output);
      $this->assertStringContainsString('Front-end dependencies processed', $output);
    }

    if ($config['has_package_lock'] && !($config['has_skip_npm_build'] ?? FALSE) && $config['has_nvmrc']) {
      $expected_note = $config['has_nvm'] ? 'Using the Node.js version in .nvmrc with nvm from %s.' : 'nvm is not installed in %s; using the Node.js on PATH, not the version in .nvmrc.';
      $this->assertStringContainsString(sprintf($expected_note, $config['nvm_dir']), $output);
      $this->assertSame($config['has_nvm'], in_array('NVM_DIR=' . $config['nvm_dir'], $this->putenvCalls, TRUE), 'NVM_DIR is exported only when nvm is installed.');
    }

    $extension_require = $config['extension_require'] ?? [];
    $extension_require_dev = $config['extension_require_dev'] ?? [];
    foreach (array_keys($config['suggestions'] ?? []) as $suggest) {
      if (isset($extension_require[$suggest]) || isset($extension_require_dev[$suggest])) {
        $this->assertStringContainsString(sprintf('Skipping %s (already in require or require-dev)', $suggest), $output);
      }
    }
    $this->assertStringContainsString('Suggested dependencies installed', $output);
  }

  public static function dataProviderAssembleSuccess(): \Iterator {
    yield 'default Drupal 11 module' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'Drupal 10 theme with existing build dir' => [
      'env' => ['DRUPAL_VERSION' => '10'],
      'config' => [
        'drupal_version' => '10',
        'drupal_release' => '10.6.18',
        'extension_type' => 'theme',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => TRUE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'with patches and GitHub token' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => 'ghp_test123',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => TRUE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'with suggested dependencies' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => ['drupal/token' => 'Token support', 'drupal/pathauto' => 'Pathauto'],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'suggested dependency overlaps with require-dev' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'extension_require_dev' => ['drupal/token' => '^1.17'],
        'suggestions' => ['drupal/token' => 'Token support', 'drupal/pathauto' => 'Pathauto'],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'suggested dependency overlaps with require' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'extension_require' => ['drupal/pathauto' => '^1.13'],
        'suggestions' => ['drupal/token' => 'Token support', 'drupal/pathauto' => 'Pathauto'],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'all suggested dependencies overlap and are skipped' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'extension_require' => ['drupal/pathauto' => '^1.13'],
        'extension_require_dev' => ['drupal/token' => '^1.17'],
        'suggestions' => ['drupal/token' => 'Token support', 'drupal/pathauto' => 'Pathauto'],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'with NPM build' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => TRUE,
        'has_skip_npm_build' => FALSE,
        'has_nvmrc' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'with NPM build and nvmrc' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => TRUE,
        'has_skip_npm_build' => FALSE,
        'has_nvmrc' => TRUE,
        'has_nvm' => TRUE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'with NPM build and nvmrc without nvm' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => TRUE,
        'has_skip_npm_build' => FALSE,
        'has_nvmrc' => TRUE,
        'has_nvm' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'with NPM build and existing node_modules' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => TRUE,
        'has_skip_npm_build' => FALSE,
        'has_node_modules' => TRUE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'with skip NPM build' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => TRUE,
        'has_skip_npm_build' => TRUE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'Drupal version with stability modifier' => [
      'env' => ['DRUPAL_VERSION' => '11@beta'],
      'config' => [
        'drupal_version' => '11@beta',
        'drupal_release' => '11.5.0-beta1',
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'with deprecation notices disabled' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'has_deprecations_disabled' => TRUE,
        'has_polyfill_bootstrap' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'with deprecation notices disabled and polyfill bootstrap' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'has_deprecations_disabled' => TRUE,
        'has_polyfill_bootstrap' => TRUE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'with all tool files' => [
      'env' => [],
      'config' => [
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'tool_files' => ['phpcs.xml', 'phpstan.neon', 'phpmd.xml', 'rector.php', '.twig-cs-fixer.php', 'phpunit.xml'],
      ],
    ];
    yield 'with version-specific phpunit' => [
      'env' => ['DRUPAL_VERSION' => '10'],
      'config' => [
        'drupal_version' => '10',
        'drupal_release' => '10.6.18',
        'extension_type' => 'module',
        'github_token' => '',
        'suggestions' => [],
        'has_build_dir' => FALSE,
        'has_patches' => FALSE,
        'has_package_lock' => FALSE,
        'has_skip_npm_build' => FALSE,
        'has_version_specific_phpunit' => TRUE,
        'tool_files' => ['phpcs.xml', 'phpunit.xml'],
      ],
    ];
    yield 'full complexity: all features enabled' => [
      'env' => ['DRUPAL_VERSION' => '10'],
      'config' => [
        'drupal_version' => '10',
        'drupal_release' => '10.6.18',
        'extension_type' => 'theme',
        'github_token' => 'ghp_fulltest',
        'suggestions' => ['drupal/token' => 'Token'],
        'has_build_dir' => TRUE,
        'has_patches' => TRUE,
        'has_package_lock' => TRUE,
        'has_skip_npm_build' => FALSE,
        'has_nvmrc' => TRUE,
        'has_nvm' => TRUE,
        'has_deprecations_disabled' => TRUE,
        'has_polyfill_bootstrap' => TRUE,
        'has_version_specific_phpunit' => TRUE,
        'tool_files' => ['phpcs.xml', 'phpstan.neon', 'phpmd.xml', 'rector.php', '.twig-cs-fixer.php', 'phpunit.xml'],
      ],
    ];
  }

  /**
   * @param array<int, string> $releases
   */
  #[DataProvider('dataProviderAssembleDependencyResolution')]
  public function testAssembleDependencyResolution(string $drupal_version, array $releases, string $expected_release, ?string $expected_phpunit, ?array $expected_preferred_install): void {
    $this->envSet('DRUPAL_VERSION', $drupal_version);
    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $this->setupAssembleMocks(['drupal_version' => $drupal_version, 'drupal_release' => $expected_release, 'releases' => $releases]);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
    $output = (string) ob_get_clean();

    $this->assertStringContainsString('Resolved Drupal ' . $drupal_version . ' to ' . $expected_release . '.', $output);

    $last_write = end($this->capturedBuildComposerJson);
    $this->assertIsString($last_write);

    $build_json = json_decode($last_write, TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertIsArray($build_json);
    /** @var array{'minimum-stability': string, 'prefer-stable': bool, require: array<string, string>, 'require-dev': array<string, string>, config: array<string, mixed>} $build_json */

    $this->assertSame('dev', $build_json['minimum-stability']);
    $this->assertTrue($build_json['prefer-stable']);
    $this->assertSame($expected_release, $build_json['require']['drupal/core-recommended']);
    $this->assertSame($expected_release, $build_json['require']['drupal/core-composer-scaffold']);
    $this->assertSame($expected_phpunit, $build_json['require-dev']['phpunit/phpunit'] ?? NULL);
    $this->assertSame($expected_preferred_install, $build_json['config']['preferred-install'] ?? NULL);
  }

  public static function dataProviderAssembleDependencyResolution(): \Iterator {
    $d12_source = ['drupal/core' => 'source'];
    // Drupal 12.0 is stable and Drupal 12.1 has a pre-release.
    $d12_later = ['12.1.0-beta1', '12.0.2', '12.0.0', '12.0.0-beta1'];

    yield 'Drupal 10' => ['10', self::RELEASES, '10.6.18', NULL, NULL];
    yield 'Drupal 11' => ['11', self::RELEASES, '11.4.8', NULL, NULL];
    yield 'Drupal 11 legacy minor' => ['11.1.0', self::RELEASES, '11.1.10', NULL, NULL];
    yield 'Drupal 11 canary' => ['11@beta', self::RELEASES, '11.5.0-beta1', NULL, NULL];
    yield 'Drupal 11 minor without a stable release' => ['11.5.0', self::RELEASES, '11.5.0-beta1', NULL, NULL];
    yield 'Drupal 12 without a stable release' => ['12', self::RELEASES, '12.0.0-beta1', '^12', $d12_source];
    yield 'Drupal 12 legacy minor without a stable release' => ['12.0.0', self::RELEASES, '12.0.0-beta1', '^12', $d12_source];
    yield 'Drupal 12 canary without a stable release' => ['12@beta', self::RELEASES, '12.0.0-beta1', '^12', $d12_source];
    yield 'Drupal 12 with a stable release' => ['12', $d12_later, '12.0.2', '^12', $d12_source];
    yield 'Drupal 12 legacy minor with a stable release' => ['12.0.0', $d12_later, '12.0.2', '^12', $d12_source];
    yield 'Drupal 12 canary with a stable release' => ['12@beta', $d12_later, '12.1.0-beta1', '^12', $d12_source];
  }

  public function testAssembleConfiguresDependencyPatches(): void {
    $this->envSet('DRUPAL_VERSION', '11');
    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $dev_composer_json = json_decode(File::read(dirname(__DIR__, 4) . '/composer.dev.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertIsArray($dev_composer_json);
    /** @var array{extra: array<string, mixed>} $dev_composer_json */
    $dev_composer_json['extra']['patches'] = ['drupal/core' => ['Patch from composer.dev.json' => 'patches/dev.patch']];

    $this->setupAssembleMocks([
      'dev_composer_json' => $dev_composer_json,
      'extension_extra' => ['patches' => ['drupal/core' => ['Patch from composer.json' => 'patches/extension.patch']]],
    ]);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
    ob_end_clean();

    $last_write = end($this->capturedBuildComposerJson);
    $this->assertIsString($last_write);

    $build_json = json_decode($last_write, TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertIsArray($build_json);
    /** @var array{'require-dev': array<string, string>, config: array{'allow-plugins': array<string, bool>}, extra: array{patches: array<string, array<string, string>>}} $build_json */

    $this->assertArrayHasKey('cweagans/composer-patches', $build_json['require-dev']);
    $this->assertTrue($build_json['config']['allow-plugins']['cweagans/composer-patches'] ?? FALSE);
    $this->assertSame(['Patch from composer.json' => 'patches/extension.patch', 'Patch from composer.dev.json' => 'patches/dev.patch'], $build_json['extra']['patches']['drupal/core']);
  }

  /**
   * @param array<string, string> $installed_packages
   * @param array<int, string> $expected_lines
   */
  #[DataProvider('dataProviderAssembleListsDevelopmentBranches')]
  public function testAssembleListsDevelopmentBranches(array $installed_packages, array $expected_lines): void {
    $this->envSet('DRUPAL_VERSION', '12');
    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $this->setupAssembleMocks(['drupal_version' => '12', 'drupal_release' => '12.0.0-beta1', 'installed_packages' => $installed_packages]);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
    $output = (string) ob_get_clean();

    if ($expected_lines === []) {
      $this->assertStringNotContainsString('Dependencies installed from development branches', $output);

      return;
    }

    $this->assertStringContainsString('Dependencies installed from development branches:', $output);
    foreach ($expected_lines as $expected_line) {
      $this->assertStringContainsString($expected_line, $output);
    }
  }

  public static function dataProviderAssembleListsDevelopmentBranches(): \Iterator {
    yield 'development branches' => [
      ['drupal/core' => '12.0.0-beta1', 'drush/drush' => '14.x-dev', 'grasmash/yaml-cli' => '4.x-dev'],
      ['drush/drush 14.x-dev', 'grasmash/yaml-cli 4.x-dev'],
    ];
    yield 'releases only' => [['drupal/core' => '12.0.0-beta1', 'drush/drush' => '14.0.0'], []];
  }

  public function testAssembleLeavesToolingOutOfBuild(): void {
    $this->envSet('DRUPAL_VERSION', '11');
    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $patch = ['Fix' => 'patches/fix.patch'];
    $this->setupAssembleMocks([
      'dev_composer_json' => [
        'require-dev' => ['drupal/coder' => '^8', 'drevops/eddy-tooling' => '~1.0.0'],
        'extra' => [
          'eddy' => ['drupal-version' => '11'],
          'patches' => ['drupal/coder' => $patch, 'drevops/eddy-tooling' => $patch],
        ],
      ],
    ]);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
    ob_end_clean();

    $last_write = end($this->capturedBuildComposerJson);
    $this->assertIsString($last_write);
    $build_json = json_decode($last_write, TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertIsArray($build_json);
    /** @var array{'require-dev': array<string, string>, extra: array<string, mixed>} $build_json */

    $this->assertArrayHasKey('drupal/coder', $build_json['require-dev']);
    $this->assertArrayNotHasKey('drevops/eddy-tooling', $build_json['require-dev']);
    $this->assertSame(['drupal/coder' => $patch], $build_json['extra']['patches']);
    $this->assertArrayNotHasKey('eddy', $build_json['extra']);
  }

  #[DataProvider('dataProviderAssembleDrupalVersionDefault')]
  public function testAssembleDrupalVersionDefault(?string $env_version, array $extra, string $expected_version, string $expected_release): void {
    if ($env_version === NULL) {
      $this->envUnset('DRUPAL_VERSION');
    }
    else {
      $this->envSet('DRUPAL_VERSION', $env_version);
    }

    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $this->setupAssembleMocks([
      'drupal_version' => $expected_version,
      'drupal_release' => $expected_release,
      'dev_composer_json' => ['require-dev' => ['drupal/coder' => '^8'], 'extra' => $extra],
    ]);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
    $output = (string) ob_get_clean();

    $this->assertStringContainsString('Resolved Drupal ' . $expected_version . ' to ' . $expected_release . '.', $output);
    $this->assertStringContainsString('Creating Drupal ' . $expected_version . ' project', $output);
  }

  public static function dataProviderAssembleDrupalVersionDefault(): \Iterator {
    yield 'project default' => [NULL, ['eddy' => ['drupal-version' => '10']], '10', '10.6.18'];
    yield 'project default with a stability flag' => [NULL, ['eddy' => ['drupal-version' => '12@beta']], '12@beta', '12.0.0-beta1'];
    yield 'no project default' => [NULL, [], '11', '11.4.8'];
    yield 'environment overrides the project default' => ['11', ['eddy' => ['drupal-version' => '10']], '11', '11.4.8'];
  }

  #[DataProvider('dataProviderAssembleReleaseFailure')]
  public function testAssembleReleaseFailure(string $drupal_version, int $releases_result_code, string $expected_message): void {
    $this->envSet('DRUPAL_VERSION', $drupal_version);
    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $this->setupAssembleMocks(['drupal_version' => $drupal_version, 'drupal_release' => NULL, 'releases_result_code' => $releases_result_code, 'has_build_dir' => TRUE]);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = (string) ob_get_clean();
    }

    $this->assertStringContainsString($expected_message, $output);
    $this->assertStringNotContainsString('Removing existing build directory', $output);
    $this->assertStringNotContainsString('Creating Drupal', $output);
  }

  public static function dataProviderAssembleReleaseFailure(): \Iterator {
    yield 'releases cannot be listed' => ['11', 1, 'Unable to list the Drupal releases.'];
    yield 'no release matches' => ['13', 0, 'No Drupal release matches 13.'];
  }

  public function testAssembleCreateProjectFailure(): void {
    $this->envSet('DRUPAL_VERSION', '11');
    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $this->setupAssembleMocks(['create_project_result_code' => 1]);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = (string) ob_get_clean();
    }

    $this->assertStringContainsString('Unable to create the Drupal 11.4.8 project.', $output);
    $this->assertStringNotContainsString('Drupal project created', $output);
  }

  #[DataProvider('dataProviderAssembleComposerAuth')]
  public function testAssembleComposerAuth(string $github_token, string $composer_auth, ?string $expected_auth): void {
    $this->envSet('DRUPAL_VERSION', '11');
    $this->envSet('GITHUB_TOKEN', $github_token);
    $this->envSet('COMPOSER_AUTH', $composer_auth);
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $this->setupAssembleMocks(['github_token' => $github_token]);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
    $output = (string) ob_get_clean();

    $auth_calls = array_values(array_filter($this->putenvCalls, static fn(string $assignment): bool => str_starts_with($assignment, 'COMPOSER_AUTH=')));
    $this->assertSame($expected_auth === NULL ? [] : ['COMPOSER_AUTH=' . $expected_auth], $auth_calls);
    $this->assertSame($expected_auth !== NULL, str_contains($output, 'GitHub token passed to Composer.'));
  }

  public static function dataProviderAssembleComposerAuth(): \Iterator {
    yield 'token' => ['ghp_test123', '', '{"github-oauth":{"github.com":"ghp_test123"}}'];
    yield "token next to the caller's COMPOSER_AUTH" => ['ghp_test123', '{"github-oauth":{"github.com":"own"}}', NULL];
    yield 'no token' => ['', '', NULL];
  }

  #[DataProvider('dataProviderAssemblePhpRequirement')]
  public function testAssemblePhpRequirement(?string $php_requirement, string $expected_message): void {
    $this->envSet('DRUPAL_VERSION', '11');
    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $this->setupAssembleMocks(['php_requirement' => $php_requirement]);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
    $output = (string) ob_get_clean();

    $this->assertStringContainsString('Checking PHP ' . self::phpVersion() . ' against Drupal 11.4.8.', $output);
    $this->assertStringContainsString(sprintf($expected_message, self::phpVersion()), $output);
    $this->assertStringContainsString('ASSEMBLE COMPLETE', $output);
  }

  public static function dataProviderAssemblePhpRequirement(): \Iterator {
    $supported = 'Drupal 11.4.8 supports PHP %s.';
    $skipped = 'Skipped the check: the PHP requirement of Drupal 11.4.8 is unknown. Composer checks it during the install.';

    yield 'supported' => ['>=8.1', $supported];
    yield 'supported by 1 of the alternatives' => ['<8.1 || >=8.1', $supported];
    yield 'core lookup fails' => [NULL, $skipped];
    yield 'constraint the check does not read' => ['^8.1', $skipped];
  }

  public function testAssemblePhpRequirementFailure(): void {
    $this->envSet('DRUPAL_VERSION', '12');
    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $this->setupAssembleMocks(['drupal_version' => '12', 'drupal_release' => '12.0.0-beta1', 'php_requirement' => '>=99', 'expect_php_failure' => TRUE, 'has_build_dir' => TRUE]);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = (string) ob_get_clean();
    }

    $this->assertStringContainsString(sprintf('Drupal 12.0.0-beta1 requires PHP >=99, but the build runs on PHP %1$s. Run the build on a PHP version that meets the requirement, or set DRUPAL_VERSION to a Drupal version that supports PHP %1$s.', self::phpVersion()), $output);
    $this->assertStringNotContainsString('Removing existing build directory', $output);
    $this->assertStringNotContainsString('Creating Drupal', $output);
  }

  #[DataProvider('dataProviderAssemblePinsPhpPlatform')]
  public function testAssemblePinsPhpPlatform(array $extension_config, array $dev_composer_json, ?string $expected_platform): void {
    $this->envSet('DRUPAL_VERSION', '11');
    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $this->setupAssembleMocks(['extension_config' => $extension_config, 'dev_composer_json' => $dev_composer_json]);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
    $output = (string) ob_get_clean();

    $expected_check = $expected_platform === NULL ? sprintf('Drupal 11.4.8 supports PHP %s.', self::phpVersion()) : sprintf('Drupal 11.4.8 supports PHP %s and the platform PHP %s.', self::phpVersion(), $expected_platform);
    $this->assertStringContainsString($expected_check, $output);

    $expected_platform ??= self::phpVersion();
    $this->assertStringContainsString('PHP platform set to ' . $expected_platform . '.', $output);

    $last_write = end($this->capturedBuildComposerJson);
    $this->assertIsString($last_write);
    $build_json = json_decode($last_write, TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertIsArray($build_json);
    /** @var array{config: array{platform: array{php: string}}} $build_json */

    $this->assertSame($expected_platform, $build_json['config']['platform']['php']);
  }

  public static function dataProviderAssemblePinsPhpPlatform(): \Iterator {
    $dev = ['require-dev' => ['drupal/coder' => '^8']];

    yield 'running PHP' => [[], $dev, NULL];
    yield 'platform from composer.json' => [['platform' => ['php' => '8.3.0']], $dev, '8.3.0'];
    yield 'platform from composer.dev.json' => [[], $dev + ['config' => ['platform' => ['php' => '8.4.0']]], '8.4.0'];
    yield 'composer.dev.json over composer.json' => [['platform' => ['php' => '8.3.0']], $dev + ['config' => ['platform' => ['php' => '8.4.0']]], '8.4.0'];
    yield 'empty platform' => [['platform' => ['php' => '']], $dev, NULL];
  }

  public function testAssemblePlatformRequirementFailure(): void {
    $this->envSet('DRUPAL_VERSION', '11');
    $this->envSet('GITHUB_TOKEN', '');
    $this->envSet('SYMFONY_DEPRECATIONS_HELPER', '');

    $this->setupAssembleMocks(['extension_config' => ['platform' => ['php' => '8.1.0']], 'php_requirement' => '>=8.3', 'expect_php_failure' => TRUE, 'has_build_dir' => TRUE]);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = (string) ob_get_clean();
    }

    $this->assertStringContainsString('Drupal 11.4.8 requires PHP >=8.3, but config.platform.php in composer.dev.json or composer.json resolves the build for PHP 8.1.0. Raise or remove that setting, or set DRUPAL_VERSION to a Drupal version that supports PHP 8.1.0.', $output);
    $this->assertStringNotContainsString('Removing existing build directory', $output);
    $this->assertStringNotContainsString('Creating Drupal', $output);
  }

  public function testAssembleMissingComposerJson(): void {
    $this->envUnset('GITHUB_TOKEN');
    $this->envUnset('SYMFONY_DEPRECATIONS_HELPER');

    $this->registerMock('file_exists', 'DrevOps\\Eddy\\Tooling', fn(string $file): false => FALSE);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-assemble';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('ASSEMBLE', $output);
      $this->assertStringContainsString('Missing composer.json file', $output);
    }
  }

  /**
   * Build the command that 'composer_show()' runs.
   */
  protected static function composerShowCommand(string $package, string $version = ''): string {
    return 'composer show --all --format=json ' . escapeshellarg($package) . ($version === '' ? '' : ' ' . escapeshellarg($version));
  }

  /**
   * Get the PHP version that runs the tests, as the build reports it.
   */
  protected static function phpVersion(): string {
    return sprintf('%d.%d.%d', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION);
  }

}
