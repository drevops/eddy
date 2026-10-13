<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Traits\GitTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

use function eddy_skills;
use function get_files;
use function main;
use function process;

/**
 * In-process tests for the high-level orchestration functions in install.php.
 *
 * The functional 'InstallTest' exercises these paths end-to-end via a
 * subprocess and PCOV cannot capture coverage from there. The tests in
 * this class run the same logic in-process so PCOV records it.
 *
 * 'remove_self' is always passed as FALSE: '__FILE__' inside 'install.php'
 * resolves to the path of the loaded project root copy, not the copy inside
 * SUT. Passing TRUE would delete the source 'install.php'.
 */
#[Group('p0')]
final class InstallProcessTest extends UnitTestCase {

  use GitTrait;

  protected string $originalCwd;

  public static function setUpBeforeClass(): void {
    putenv('SCRIPT_RUN_SKIP=1');
    require_once dirname(__DIR__, 4) . '/install.php';
    parent::setUpBeforeClass();
  }

  protected function setUp(): void {
    parent::setUp();
    $this->originalCwd = getcwd() ?: '/';
    $project_root = dirname(__DIR__, 4);
    self::locationsCopy($project_root, self::$sut, [], [
      '.idea',
      '.logs',
      '.phpunit.cache',
      '.artifacts',
      'build',
    ]);
    chdir(self::$sut);
  }

  protected function tearDown(): void {
    chdir($this->originalCwd);
    parent::tearDown();
  }

  /**
   * @param array<string> $command_wrapper
   * @param array<string> $expected_exists
   * @param array<string> $expected_not_exists
   * @param array<string> $expected_claude_allow
   */
  #[DataProvider('dataProviderProcess')]
  public function testProcess(string $name, string $machine_name, string $type, array $command_wrapper, array $expected_exists, array $expected_not_exists, array $expected_claude_allow, bool $info_yml_has_base_theme): void {
    process($name, $machine_name, $type, ['10', '11'], $command_wrapper, [], FALSE, FALSE);

    foreach ($expected_exists as $path) {
      $this->assertFileExists(self::$sut . '/' . $path, 'Expected to exist: ' . $path);
    }

    foreach ($expected_not_exists as $path) {
      $this->assertFileDoesNotExist(self::$sut . '/' . $path, 'Expected not to exist: ' . $path);
    }

    $claude_path = self::$sut . '/.claude/settings.json';
    $claude_content = file_get_contents($claude_path);
    $this->assertNotFalse($claude_content);
    $decoded = json_decode($claude_content, TRUE);
    $this->assertSame(['permissions' => ['allow' => $expected_claude_allow, 'deny' => []]], $decoded);

    if ($info_yml_has_base_theme) {
      $info_path = self::$sut . '/' . $machine_name . '.info.yml';
      $info = file_get_contents($info_path);
      $this->assertNotFalse($info);
      $this->assertStringEndsWith('base theme: false' . PHP_EOL, $info);
    }

    $info_path = self::$sut . '/' . $machine_name . '.info.yml';
    $info = (string) file_get_contents($info_path);
    $this->assertStringNotContainsString('your_extension', $info);

    $readme = (string) file_get_contents(self::$sut . '/README.md');
    $this->assertStringContainsString($name, $readme);
    $this->assertStringContainsString('[Eddy](https://github.com/drevops/eddy)', $readme);
  }

  public static function dataProviderProcess(): \Iterator {
    $module_exists = [
      'my_extension.info.yml',
      'my_extension.install',
      'my_extension.libraries.yml',
      'my_extension.links.menu.yml',
      'my_extension.module',
      'my_extension.routing.yml',
      'my_extension.services.yml',
      'config/schema/my_extension.schema.yml',
      'src/Form/MyExtensionForm.php',
      'src/MyExtensionService.php',
      'tests/src/Unit/MyExtensionServiceUnitTest.php',
      'tests/src/Kernel/MyExtensionServiceKernelTest.php',
      'tests/src/Functional/MyExtensionFunctionalTest.php',
      'tests/src/FunctionalJavascript/MyExtensionFunctionalJavascriptTestBase.php',
      'tests/src/FunctionalJavascript/MyExtensionSmokeFunctionalJavascriptTest.php',
      'css/my_extension.css',
      'js/my_extension.js',
      'js/my_extension.test.js',
      'README.md',
      'CONTRIBUTING.md',
      'install.php',
    ];

    $module_not_exists = [
      'your_extension.info.yml',
      'your_extension.install',
      'your_extension.module',
      'src/YourExtensionService.php',
      'src/Form/YourExtensionForm.php',
      'README.dist.md',
      'CONTRIBUTING.dist.md',
      'LICENSE.txt',
      'MIGRATION.md',
      'SECURITY.md',
      'logo.svg',
      '.eddy',
      '.claude/skills/create-eddy-tooling-release-notes',
    ];

    yield 'module, gha, ahoy' => [
      'My Extension', 'my_extension', 'module', ['ahoy'],
      array_merge($module_exists, ['.github/workflows', '.ahoy.yml']),
      array_merge($module_not_exists, ['.circleci', 'Makefile']),
      ['Bash(ahoy:*)', 'Bash(composer:*)'],
      FALSE,
    ];

    yield 'theme' => [
      'My Theme', 'my_theme', 'theme', ['ahoy'],
      [
        'my_theme.info.yml',
        'my_theme.libraries.yml',
        'README.md',
        'CONTRIBUTING.md',
        '.github/workflows',
        '.ahoy.yml',
      ],
      [
        'your_extension.info.yml',
        'my_theme.install',
        'my_theme.module',
        'my_theme.routing.yml',
        'my_theme.services.yml',
        'my_theme.links.menu.yml',
        'src/MyThemeService.php',
        '.circleci',
        'Makefile',
      ],
      ['Bash(ahoy:*)', 'Bash(composer:*)'],
      TRUE,
    ];

    yield 'module, gha, makefile only' => [
      'My Extension', 'my_extension', 'module', ['makefile'],
      array_merge($module_exists, ['.github/workflows', 'Makefile']),
      array_merge($module_not_exists, ['.circleci', '.ahoy.yml']),
      ['Bash(composer:*)', 'Bash(make:*)'],
      FALSE,
    ];

    yield 'module, gha, both wrappers' => [
      'My Extension', 'my_extension', 'module', ['ahoy', 'makefile'],
      array_merge($module_exists, ['.github/workflows', '.ahoy.yml', 'Makefile']),
      array_merge($module_not_exists, ['.circleci']),
      ['Bash(ahoy:*)', 'Bash(composer:*)', 'Bash(make:*)'],
      FALSE,
    ];

    yield 'module, gha, no wrappers' => [
      'My Extension', 'my_extension', 'module', [],
      array_merge($module_exists, ['.github/workflows']),
      array_merge($module_not_exists, ['.circleci', '.ahoy.yml', 'Makefile']),
      ['Bash(composer:*)'],
      FALSE,
    ];
  }

  /**
   * @param array<string> $tools_remove
   * @param array<string> $expected_not_exists
   * @param array<string> $expected_composer_dev_absent
   * @param array<string> $expected_package_json_absent
   * @param array<string> $expected_pipeline_absent
   */
  #[DataProvider('dataProviderProcessRemovesTools')]
  public function testProcessRemovesTools(array $tools_remove, array $expected_not_exists, array $expected_composer_dev_absent, array $expected_package_json_absent, array $expected_pipeline_absent): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy', 'makefile'], $tools_remove, FALSE, FALSE);

    foreach ($expected_not_exists as $path) {
      $this->assertFileDoesNotExist(self::$sut . '/' . $path, 'Expected removed: ' . $path);
    }

    $composer_dev = (string) file_get_contents(self::$sut . '/composer.dev.json');
    foreach ($expected_composer_dev_absent as $needle) {
      $this->assertStringNotContainsString($needle, $composer_dev, 'composer.dev.json should not contain: ' . $needle);
    }

    $package_json = is_file(self::$sut . '/package.json') ? (string) file_get_contents(self::$sut . '/package.json') : '';
    foreach ($expected_package_json_absent as $needle) {
      $this->assertStringNotContainsString($needle, $package_json, 'package.json should not contain: ' . $needle);
    }

    $ahoy = (string) file_get_contents(self::$sut . '/.ahoy.yml');
    $makefile = (string) file_get_contents(self::$sut . '/Makefile');
    $gha = (string) file_get_contents(self::$sut . '/.github/workflows/test.yml');
    foreach ($expected_pipeline_absent as $needle) {
      $this->assertStringNotContainsString($needle, $ahoy, '.ahoy.yml should not contain: ' . $needle);
      $this->assertStringNotContainsString($needle, $makefile, 'Makefile should not contain: ' . $needle);
      $this->assertStringNotContainsString($needle, $gha, 'test.yml should not contain: ' . $needle);
    }
  }

  public static function dataProviderProcessRemovesTools(): \Iterator {
    yield 'phpcs' => [
      ['phpcs'],
      ['phpcs.xml'],
      ['drupal/coder', 'drevops/phpcs-standard', 'phpcompatibility/php-compatibility'],
      [],
      ['vendor/bin/phpcs', 'vendor/bin/phpcbf'],
    ];

    yield 'phpstan' => [
      ['phpstan'],
      ['phpstan.neon'],
      ['mglaman/phpstan-drupal', 'phpstan/extension-installer'],
      [],
      ['vendor/bin/phpstan'],
    ];

    yield 'rector' => [
      ['rector'],
      ['rector.php'],
      ['palantirnet/drupal-rector'],
      [],
      ['vendor/bin/rector'],
    ];

    yield 'twigcs' => [
      ['twigcs'],
      ['.twig-cs-fixer.php'],
      ['vincentlanglet/twig-cs-fixer'],
      [],
      ['vendor/bin/twig-cs-fixer'],
    ];

    yield 'all php lint tools' => [
      ['phpcs', 'phpstan', 'rector', 'twigcs'],
      ['phpcs.xml', 'phpstan.neon', 'rector.php', '.twig-cs-fixer.php'],
      ['drupal/coder', 'mglaman/phpstan-drupal', 'palantirnet/drupal-rector', 'vincentlanglet/twig-cs-fixer'],
      [],
      ['vendor/bin/phpcs', 'vendor/bin/phpcbf', 'vendor/bin/phpstan', 'vendor/bin/rector', 'vendor/bin/twig-cs-fixer'],
    ];

    yield 'eslint' => [
      ['eslint'],
      ['.eslintrc.json', '.eslintignore', '.prettierrc.json', '.prettierignore'],
      ['[web-root]/.eslintrc.json', 'drupal-scaffold'],
      ['eslint-config-airbnb-base', 'eslint-plugin-prettier', '"prettier"', 'lint-js'],
      [],
    ];

    yield 'stylelint' => [
      ['stylelint'],
      ['.stylelintrc.js'],
      [],
      ['stylelint-config-standard', 'stylelint-order', 'lint-css'],
      [],
    ];

    yield 'cspell' => [
      ['cspell'],
      ['.cspell.json'],
      [],
      ['"cspell"', 'lint-spell'],
      ['npm run lint-spell'],
    ];

    yield 'jest' => [
      ['jest'],
      ['jest.config.js', 'js/my_extension.test.js'],
      [],
      ['jest-environment-jsdom'],
      ['npm test'],
    ];

    yield 'eslint and stylelint' => [
      ['eslint', 'stylelint'],
      ['.eslintrc.json', '.stylelintrc.js'],
      [],
      ['"eslint"', '"stylelint"', 'npm run lint-js', 'npm run lint-css'],
      ['Running ESLint', 'NodeJS linters'],
    ];

    yield 'all npm tools' => [
      ['eslint', 'stylelint', 'cspell', 'jest'],
      ['.eslintrc.json', '.stylelintrc.js', '.cspell.json', 'jest.config.js', 'js/my_extension.test.js', 'package.json', 'package-lock.json'],
      [],
      [],
      ['Running ESLint', 'NodeJS linters', 'npm run lint-spell', 'npm test', 'Setup Node.js', 'npm ci'],
    ];

    // Removing PHPUnit also removes FunctionalJavascript and its Selenium
    // driver, per the normalisation in 'remove_tools()'.
    yield 'phpunit' => [
      ['phpunit'],
      ['phpunit.xml', 'phpunit.d10.xml', 'tests'],
      ['phpunit/phpunit', 'phpspec/prophecy-phpunit', 'mikey179/vfsstream', 'lullabot/mink-selenium2-driver', 'behat/mink'],
      [],
      ['vendor/bin/phpunit', 'selenium'],
    ];

    yield 'functional_javascript' => [
      ['functional_javascript'],
      ['tests/src/FunctionalJavascript'],
      ['lullabot/mink-selenium2-driver'],
      [],
      ['selenium', 'functional-javascript'],
    ];

    yield 'renovate' => [
      ['renovate'],
      ['renovate.json'],
      [],
      [],
      [],
    ];
  }

  /**
   * Removing a tool removes exactly its 'require-dev' packages.
   *
   * @param array<string> $tools_remove
   * @param array<string> $expected
   */
  #[DataProvider('dataProviderProcessRemovesComposerDevPackages')]
  public function testProcessRemovesComposerDevPackages(array $tools_remove, array $expected): void {
    $before = self::composerDevPackages();

    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], $tools_remove, FALSE, FALSE);

    $removed = array_values(array_diff($before, self::composerDevPackages()));
    sort($removed);
    sort($expected);

    $this->assertSame($expected, $removed);
  }

  public static function dataProviderProcessRemovesComposerDevPackages(): \Iterator {
    $phpunit = [
      'phpunit/phpunit',
      'phpspec/prophecy-phpunit',
      'mikey179/vfsstream',
      'behat/mink',
      'behat/mink-browserkit-driver',
      'symfony/browser-kit',
      'symfony/css-selector',
      'symfony/dom-crawler',
    ];

    yield 'no tool' => [[], []];
    yield 'functional_javascript' => [['functional_javascript'], ['lullabot/mink-selenium2-driver']];
    yield 'phpunit' => [['phpunit'], [...$phpunit, 'lullabot/mink-selenium2-driver']];
    yield 'phpunit and functional_javascript' => [['phpunit', 'functional_javascript'], [...$phpunit, 'lullabot/mink-selenium2-driver']];
  }

  /**
   * The WebDriver settings in the PHPUnit files follow FunctionalJavascript.
   *
   * @param array<string> $tools_remove
   */
  #[DataProvider('dataProviderProcessPrunesWebdriverSettings')]
  public function testProcessPrunesWebdriverSettings(array $tools_remove, bool $expect): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], $tools_remove, FALSE, FALSE);

    foreach (['phpunit.xml', 'phpunit.d10.xml'] as $file) {
      $content = (string) file_get_contents(self::$sut . '/' . $file);
      $document = new \DOMDocument();
      $this->assertTrue($document->loadXML($content), $file . ' is not valid XML.');

      $this->assertSame($expect ? 1 : 0, self::countNodes($content, '//php/env[@name="MINK_DRIVER_ARGS_WEBDRIVER"]'), $file . ' MINK_DRIVER_ARGS_WEBDRIVER presence mismatch.');
      $this->assertSame($expect ? 1 : 0, self::countNodes($content, '//testsuite[@name="functional-javascript"]'), $file . ' functional-javascript suite presence mismatch.');
      $this->assertSame($expect, stripos($content, 'webdriver') !== FALSE, $file . ' WebDriver text presence mismatch.');
      // 'BrowserTestBase' reads these for Functional tests too.
      $this->assertSame(1, self::countNodes($content, '//php/env[@name="MINK_DRIVER_CLASS"]'), $file . ' misses MINK_DRIVER_CLASS.');
      $this->assertSame(1, self::countNodes($content, '//php/env[@name="MINK_DRIVER_ARGS"]'), $file . ' misses MINK_DRIVER_ARGS.');
      $this->assertStringNotContainsString('#;', $content, $file . ' keeps a marker line.');
    }
  }

  public static function dataProviderProcessPrunesWebdriverSettings(): \Iterator {
    yield 'FunctionalJavascript' => [[], TRUE];
    yield 'no FunctionalJavascript' => [['functional_javascript'], FALSE];
  }

  /**
   * The 'info' lines in 'AGENTS.md' name the WebDriver port only with
   * FunctionalJavascript.
   *
   * @param array<string> $tools_remove
   * @param array<string> $command_wrapper
   */
  #[DataProvider('dataProviderProcessRendersInfoText')]
  public function testProcessRendersInfoText(array $tools_remove, array $command_wrapper, bool $expect): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], $command_wrapper, $tools_remove, FALSE, FALSE);

    $agents = (string) file_get_contents(self::$sut . '/AGENTS.md');
    $items = $expect ? 'XDebug state, WebDriver port, build directory' : 'XDebug state, build directory';

    preg_match_all('/^- `[^`]+` - Print a read-only summary of .+$/m', $agents, $matches);
    $this->assertCount(max(1, count($command_wrapper)), $matches[0], 'AGENTS.md info line count mismatch.');

    foreach ($matches[0] as $line) {
      $this->assertStringContainsString('site URL, ' . $items . ', database path', $line);
    }

    $this->assertSame($expect, stripos($agents, 'webdriver') !== FALSE, 'AGENTS.md WebDriver text presence mismatch.');
  }

  public static function dataProviderProcessRendersInfoText(): \Iterator {
    $wrappers = ['both wrappers' => ['ahoy', 'makefile'], 'ahoy only' => ['ahoy'], 'makefile only' => ['makefile'], 'no wrappers' => []];

    foreach ($wrappers as $name => $command_wrapper) {
      yield $name . ', FunctionalJavascript' => [[], $command_wrapper, TRUE];
      yield $name . ', no FunctionalJavascript' => [['functional_javascript'], $command_wrapper, FALSE];
      yield $name . ', no PHPUnit' => [['phpunit'], $command_wrapper, FALSE];
    }
  }

  /**
   * The 'Makefile' 'test' recipe exports the WebDriver port only with
   * FunctionalJavascript.
   *
   * @param array<string> $tools_remove
   */
  #[DataProvider('dataProviderProcessRendersMakefileTestRecipe')]
  public function testProcessRendersMakefileTestRecipe(array $tools_remove, bool $expect_phpunit, bool $expect_webdriver): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['makefile'], $tools_remove, FALSE, FALSE);

    $makefile = (string) file_get_contents(self::$sut . '/Makefile');
    $export = "\t" . 'export WEBDRIVER_PORT="$$(vendor/bin/eddy-info webdriver-port)" && \\' . "\n";
    $phpunit = "\t" . 'pushd "build" >/dev/null || exit 1 && \\' . "\n\t" . 'php -d pcov.directory=.. vendor/bin/phpunit $(TEST_RUN_ARGS) && \\' . "\n\t" . 'popd >/dev/null || exit 1' . "\n";
    $recipe = "\t" . '$(call title,Running PHPUnit)' . "\n" . ($expect_webdriver ? $export : '') . $phpunit;

    $this->assertSame($expect_phpunit, str_contains($makefile, $recipe), 'Makefile PHPUnit recipe line mismatch.');
    $this->assertSame($expect_webdriver, stripos($makefile, 'webdriver') !== FALSE, 'Makefile WebDriver text presence mismatch.');
  }

  public static function dataProviderProcessRendersMakefileTestRecipe(): \Iterator {
    yield 'FunctionalJavascript' => [[], TRUE, TRUE];
    yield 'no FunctionalJavascript' => [['functional_javascript'], TRUE, FALSE];
    yield 'no PHPUnit' => [['phpunit'], FALSE, FALSE];
  }

  /**
   * The browser setup in CI and in '.gitignore' follows FunctionalJavascript.
   *
   * @param array<string> $tools_remove
   */
  #[DataProvider('dataProviderProcessPrunesBrowserSetup')]
  public function testProcessPrunesBrowserSetup(array $tools_remove, bool $expect): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], $tools_remove, FALSE, FALSE);

    $workflow = (string) file_get_contents(self::$sut . '/.github/workflows/test.yml');
    $gitignore = (string) file_get_contents(self::$sut . '/.gitignore');

    $this->assertSame($expect, str_contains($workflow, "\n    services:\n"), 'test.yml services presence mismatch.');
    $this->assertSame($expect, str_contains($workflow, 'selenium'), 'test.yml Selenium presence mismatch.');
    $this->assertStringNotContainsString("\n\n\n", $workflow, 'test.yml holds a double blank line.');
    $this->assertSame($expect, preg_match('/^\/\.chromedriver$/m', $gitignore) === 1, '.gitignore chromedriver entry presence mismatch.');
    $this->assertStringNotContainsString('#;', $workflow . $gitignore, 'A file keeps a marker line.');
  }

  public static function dataProviderProcessPrunesBrowserSetup(): \Iterator {
    yield 'FunctionalJavascript' => [[], TRUE];
    yield 'no FunctionalJavascript' => [['functional_javascript'], FALSE];
    yield 'no PHPUnit' => [['phpunit'], FALSE];
  }

  /**
   * The example opt-out removes only the example lifecycle scripts.
   */
  #[DataProvider('dataProviderProcessExamples')]
  public function testProcessExamples(bool $remove_examples, bool $expect_exists): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], [], $remove_examples, FALSE);

    foreach (['assemble', 'provision', 'start', 'stop'] as $phase) {
      $path = self::$sut . '/scripts/' . $phase . '-example.sh';
      if ($expect_exists) {
        $this->assertFileExists($path, 'Expected to keep: ' . $path);
      }
      else {
        $this->assertFileDoesNotExist($path, 'Expected removed: ' . $path);
      }
    }

    // The 'scripts' directory stays as the location for project-local scripts.
    $this->assertDirectoryExists(self::$sut . '/scripts');
  }

  public static function dataProviderProcessExamples(): \Iterator {
    yield 'keep' => [FALSE, TRUE];
    yield 'remove' => [TRUE, FALSE];
  }

  /**
   * Files of an existing project outside the template stay untouched.
   *
   * @param array<string> $tools_remove
   */
  #[DataProvider('dataProviderProcessKeepsProjectFiles')]
  public function testProcessKeepsProjectFiles(array $tools_remove): void {
    // 'process()' rewrites each of these tokens in a file it walks.
    $content = "your_extension YourExtension type: module\n#;< META\nblock\n#;> META\n";
    $paths = [
      '.claude/settings.local.json',
      '.claude/skills/update-consumer-eddy/SKILL.md',
      '.claude/skills/project-skill/SKILL.md',
      '.chromedriver/chromedriver.log',
      'build/web/core/modules/system/system.info.yml',
      '.logs/your_extension.log',
      '.env',
    ];

    $filesystem = new Filesystem();
    foreach ($paths as $path) {
      $filesystem->dumpFile(self::$sut . '/' . $path, $content);
    }

    process('My Theme', 'my_theme', 'theme', ['10', '11'], ['ahoy'], $tools_remove, FALSE, FALSE);

    foreach ($paths as $path) {
      $this->assertSame($content, file_get_contents(self::$sut . '/' . $path), 'Expected untouched: ' . $path);
    }

    $this->assertStringContainsString('type: theme', (string) file_get_contents(self::$sut . '/my_theme.info.yml'));
  }

  public static function dataProviderProcessKeepsProjectFiles(): \Iterator {
    yield 'every tool' => [[]];
    // Without FunctionalJavascript, '.gitignore' no longer lists
    // '.chromedriver'.
    yield 'no FunctionalJavascript' => [['functional_javascript']];
  }

  /**
   * Eddy's skills go, and '.claude/skills' stays while it holds any other.
   *
   * @param array<int, string> $project_skills
   */
  #[DataProvider('dataProviderProcessRemovesEddySkills')]
  public function testProcessRemovesEddySkills(array $project_skills, bool $expect_skills_dir): void {
    $skills_dir = self::$sut . '/.claude/skills';
    $filesystem = new Filesystem();
    // The copy holds whatever skills the local checkout has.
    $filesystem->remove($skills_dir);

    foreach (eddy_skills() as $skill) {
      $filesystem->dumpFile($skills_dir . '/' . $skill . '/SKILL.md', 'Eddy skill');
    }

    foreach ($project_skills as $skill) {
      $filesystem->dumpFile($skills_dir . '/' . $skill . '/SKILL.md', 'Project skill');
    }

    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], [], FALSE, FALSE);

    foreach (eddy_skills() as $skill) {
      $this->assertDirectoryDoesNotExist($skills_dir . '/' . $skill);
    }

    foreach ($project_skills as $skill) {
      $this->assertSame('Project skill', file_get_contents($skills_dir . '/' . $skill . '/SKILL.md'));
    }

    $this->assertSame($expect_skills_dir, is_dir($skills_dir));
  }

  public static function dataProviderProcessRemovesEddySkills(): \Iterator {
    yield 'only Eddy skills' => [[], FALSE];
    yield 'project skills' => [['update-consumer-eddy', 'project-skill'], TRUE];
  }

  /**
   * The 'AGENTS.md' wrapper blocks follow the command wrapper selection.
   *
   * @param array<string> $command_wrapper
   */
  #[DataProvider('dataProviderProcessRemovesWrapperDocs')]
  public function testProcessRemovesWrapperDocs(array $command_wrapper, bool $expect_make, bool $expect_ahoy): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], $command_wrapper, [], FALSE, FALSE);

    $agents = (string) file_get_contents(self::$sut . '/AGENTS.md');

    $make_rule = '`make lint` / `make lint-fix` - never `vendor/bin/phpcs`';
    $ahoy_rule = '`ahoy lint` / `ahoy lint-fix` - never `vendor/bin/phpcs`';
    $make_command = '- `make build` - Complete build';
    $ahoy_command = '- `ahoy build` - Complete build';

    $this->assertSame($expect_make, str_contains($agents, $make_rule), 'AGENTS.md make rule block presence mismatch.');
    $this->assertSame($expect_ahoy, str_contains($agents, $ahoy_rule), 'AGENTS.md ahoy rule block presence mismatch.');
    $this->assertSame($expect_make, str_contains($agents, $make_command), 'AGENTS.md make command list presence mismatch.');
    $this->assertSame($expect_ahoy, str_contains($agents, $ahoy_command), 'AGENTS.md ahoy command list presence mismatch.');
    $this->assertSame($expect_make, preg_match('/\bmake\b/i', $agents) === 1, 'AGENTS.md mentions make without the Makefile, or omits it with the Makefile.');
    $this->assertSame($expect_ahoy, preg_match('/\bahoy\b/i', $agents) === 1, 'AGENTS.md mentions Ahoy without it, or omits it with it.');
    $this->assertStringNotContainsString('(default)', $agents, 'AGENTS.md calls a wrapper the default.');
    $this->assertStringNotContainsString('(alternative)', $agents, 'AGENTS.md calls a wrapper the alternative.');
  }

  public static function dataProviderProcessRemovesWrapperDocs(): \Iterator {
    yield 'both wrappers' => [['ahoy', 'makefile'], TRUE, TRUE];
    yield 'ahoy only' => [['ahoy'], FALSE, TRUE];
    yield 'makefile only' => [['makefile'], TRUE, FALSE];
    yield 'no wrappers' => [[], FALSE, FALSE];
  }

  /**
   * The 'CONTRIBUTING.md' commands follow the command wrapper selection.
   *
   * @param array<string> $command_wrapper
   */
  #[DataProvider('dataProviderProcessPrunesContributingCommands')]
  public function testProcessPrunesContributingCommands(array $command_wrapper, bool $expect_make, bool $expect_ahoy): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], $command_wrapper, [], FALSE, FALSE);

    $contributing = (string) file_get_contents(self::$sut . '/CONTRIBUTING.md');
    $expect_wrapper = $expect_make || $expect_ahoy;
    $commands = '(build|assemble|start|provision|debug|lint|test)';

    $this->assertSame($expect_make, preg_match('/\bmake ' . $commands . '/', $contributing) === 1, 'CONTRIBUTING.md make command presence mismatch.');
    $this->assertSame($expect_ahoy, preg_match('/\bahoy ' . $commands . '/', $contributing) === 1, 'CONTRIBUTING.md ahoy command presence mismatch.');
    $this->assertSame($expect_wrapper, str_contains($contributing, 'The `build` command is a wrapper'), 'CONTRIBUTING.md wrapper text presence mismatch.');
    $this->assertSame(!$expect_wrapper, str_contains($contributing, 'vendor/bin/eddy-assemble'), 'CONTRIBUTING.md direct command presence mismatch.');
    $this->assertStringNotContainsString('#;', $contributing, 'CONTRIBUTING.md keeps a marker line.');
  }

  public static function dataProviderProcessPrunesContributingCommands(): \Iterator {
    yield from self::dataProviderProcessRemovesWrapperDocs();
  }

  /**
   * The 'AGENTS.md' text that names the command wrappers follows the selection.
   *
   * @param array<string> $command_wrapper
   * @param array<string> $expected_lines
   * @param array<string> $expected_phrases
   */
  #[DataProvider('dataProviderProcessRendersWrapperText')]
  public function testProcessRendersWrapperText(array $command_wrapper, array $expected_lines, array $expected_phrases): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], $command_wrapper, [], FALSE, FALSE);

    $agents = (string) file_get_contents(self::$sut . '/AGENTS.md');

    foreach ($expected_lines as $line) {
      $this->assertMatchesRegularExpression('/^' . preg_quote($line, '/') . '$/m', $agents, 'AGENTS.md misses the line: ' . $line);
    }

    foreach ($expected_phrases as $phrase) {
      $this->assertStringContainsString($phrase, $agents);
    }
  }

  public static function dataProviderProcessRendersWrapperText(): \Iterator {
    $tooling = static fn(string $runners): string => '- `scripts/eddy-tooling` - Installs the tooling package into `vendor/` from the constraint in `composer.dev.json`. ' . $runners . ' before the tooling commands';
    $wrapper_rule = static fn(string $binaries): array => ['When ' . $binaries . ' exposes a command for a task', 'extend the ' . $binaries . ' target'];

    yield 'both wrappers' => [
      ['ahoy', 'makefile'],
      [
        '**Using Make:**',
        '**Using Ahoy:**',
        '1. Build environment: `make build` or `ahoy build`',
        '3. Check standards: `make lint` or `ahoy lint`',
        '4. Run tests: `make test` or `ahoy test`',
        '5. Access site at the URL that `make info` or `ahoy info` reports',
        $tooling('The command wrappers and CI run it'),
      ],
      $wrapper_rule('`make` or `ahoy`'),
    ];

    yield 'ahoy only' => [
      ['ahoy'],
      [
        '**Using Ahoy:**',
        '1. Build environment: `ahoy build`',
        '3. Check standards: `ahoy lint`',
        '4. Run tests: `ahoy test`',
        '5. Access site at the URL that `ahoy info` reports',
        $tooling('The command wrappers and CI run it'),
      ],
      $wrapper_rule('`ahoy`'),
    ];

    yield 'makefile only' => [
      ['makefile'],
      [
        '**Using Make:**',
        '1. Build environment: `make build`',
        '3. Check standards: `make lint`',
        '4. Run tests: `make test`',
        '5. Access site at the URL that `make info` reports',
        $tooling('The command wrappers and CI run it'),
      ],
      $wrapper_rule('`make`'),
    ];

    yield 'no wrappers' => [
      [],
      [
        '- `scripts/eddy-tooling` - Install the tooling commands into `vendor/bin/`. Run it before the commands below',
        '- `vendor/bin/eddy-assemble` - Assemble codebase with dependencies',
        '- `build/vendor/bin/drush -l "$(vendor/bin/eddy-info site-url)" <command>` - Run Drush commands',
        'Run the linters and the tests with the commands that `CONTRIBUTING.md` lists.',
        '1. Build environment: `scripts/eddy-tooling`, then `vendor/bin/eddy-assemble`, `vendor/bin/eddy-start` and `vendor/bin/eddy-provision`',
        '3. Check standards: run the linters that `CONTRIBUTING.md` lists',
        '4. Run tests: run the tests that `CONTRIBUTING.md` lists',
        '5. Access site at the URL that `vendor/bin/eddy-info site-url` prints',
        $tooling('CI runs it'),
      ],
      ['**HARD RULE - run each tool exactly as `CONTRIBUTING.md` shows.** This project has no command wrapper'],
    ];
  }

  /**
   * The '.gitattributes' and '.editorconfig' wrapper entries follow the
   * command wrapper selection.
   *
   * @param array<string> $command_wrapper
   */
  #[DataProvider('dataProviderProcessPrunesWrapperConfig')]
  public function testProcessPrunesWrapperConfig(array $command_wrapper, bool $expect_make, bool $expect_ahoy): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], $command_wrapper, [], FALSE, FALSE);

    $gitattributes = (string) file_get_contents(self::$sut . '/.gitattributes');
    $editorconfig = (string) file_get_contents(self::$sut . '/.editorconfig');

    $this->assertSame($expect_make, preg_match('/^Makefile +export-ignore$/m', $gitattributes) === 1, '.gitattributes Makefile entry presence mismatch.');
    $this->assertSame($expect_ahoy, preg_match('/^\.ahoy\.yml +export-ignore$/m', $gitattributes) === 1, '.gitattributes .ahoy.yml entry presence mismatch.');
    $this->assertSame($expect_make, str_contains($editorconfig, '[Makefile*]'), '.editorconfig Makefile section presence mismatch.');
    $this->assertStringNotContainsString('#;', $gitattributes . $editorconfig, 'A wrapper config file keeps a marker line.');
    $this->assertStringEndsNotWith("\n\n", $editorconfig, '.editorconfig ends with a blank line.');
  }

  public static function dataProviderProcessPrunesWrapperConfig(): \Iterator {
    yield from self::dataProviderProcessRemovesWrapperDocs();
  }

  /**
   * The 'AGENTS.md' overview describes the extension rather than the template.
   */
  #[DataProvider('dataProviderProcessRendersOverview')]
  public function testProcessRendersOverview(string $name, string $machine_name, string $type, string $expected): void {
    process($name, $machine_name, $type, ['10', '11'], ['ahoy'], [], FALSE, FALSE);

    $agents = (string) file_get_contents(self::$sut . '/AGENTS.md');
    $overview = preg_match('/^## Overview\n(.*?)^## /ms', $agents, $matches) === 1 ? $matches[1] : '';

    $this->assertStringStartsWith("\n" . $expected . ' The project provides', $overview);
    $this->assertStringNotContainsStringIgnoringCase('template', $overview);
  }

  public static function dataProviderProcessRendersOverview(): \Iterator {
    yield 'module' => ['My Extension', 'my_extension', 'module', 'My Extension (`my_extension`) is a Drupal module.'];
    yield 'theme' => ['My Theme', 'my_theme', 'theme', 'My Theme (`my_theme`) is a Drupal theme.'];
  }

  /**
   * Removed blocks and lines leave no run of blank lines in a project file.
   *
   * @param array<string> $drupal_versions
   * @param array<string> $command_wrapper
   * @param array<string> $tools_remove
   */
  #[DataProvider('dataProviderProcessLeavesNoDoubleBlankLines')]
  public function testProcessLeavesNoDoubleBlankLines(array $drupal_versions, array $command_wrapper, array $tools_remove): void {
    process('My Extension', 'my_extension', 'module', $drupal_versions, $command_wrapper, $tools_remove, FALSE, FALSE);

    $files = get_files();
    $this->assertNotSame([], $files, 'The project holds no file.');

    foreach ($files as $file) {
      $content = (string) file_get_contents($file);
      $path = substr($file, strlen((string) getcwd()) + 1);

      $this->assertStringNotContainsString("\n\n\n", $content, $path . ' holds a double blank line.');
      $this->assertStringStartsNotWith("\n", $content, $path . ' starts with a blank line.');
      $this->assertStringEndsNotWith("\n\n", $content, $path . ' ends with a blank line.');
    }
  }

  public static function dataProviderProcessLeavesNoDoubleBlankLines(): \Iterator {
    $tools = ['phpcs', 'phpstan', 'rector', 'twigcs', 'eslint', 'stylelint', 'cspell', 'jest', 'phpunit', 'functional_javascript', 'renovate'];

    yield 'both wrappers' => [['10', '11'], ['ahoy', 'makefile'], []];
    yield 'ahoy only' => [['10', '11'], ['ahoy'], []];
    yield 'makefile only' => [['10', '11'], ['makefile'], []];
    yield 'no wrappers' => [['10', '11'], [], []];
    yield 'all majors' => [['10', '11', '12'], ['ahoy'], []];
    yield 'd10 only' => [['10'], ['ahoy'], []];
    yield 'd11 only' => [['11'], ['ahoy'], []];
    yield 'd12 only' => [['12'], ['ahoy'], []];
    yield 'no FunctionalJavascript' => [['10', '11'], ['ahoy', 'makefile'], ['functional_javascript']];
    yield 'no PHPUnit' => [['10', '11'], ['ahoy', 'makefile'], ['phpunit']];
    yield 'no Jest' => [['10', '11'], ['ahoy', 'makefile'], ['jest']];
    yield 'no PHP lint tools' => [['10', '11'], ['ahoy', 'makefile'], ['phpcs', 'phpstan', 'rector', 'twigcs']];
    yield 'no npm tools' => [['10', '11'], ['ahoy', 'makefile'], ['eslint', 'stylelint', 'cspell', 'jest']];
    yield 'no tools' => [['10', '11'], ['ahoy', 'makefile'], $tools];
    yield 'no tools, no wrappers' => [['10', '11'], [], $tools];
  }

  /**
   * @param array<string> $tools_remove
   * @param array<string> $expected_absent
   */
  #[DataProvider('dataProviderProcessRemovesGitattributes')]
  public function testProcessRemovesGitattributes(array $tools_remove, array $expected_absent): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], $tools_remove, FALSE, FALSE);

    $gitattributes = (string) file_get_contents(self::$sut . '/.gitattributes');
    foreach ($expected_absent as $needle) {
      $this->assertStringNotContainsString($needle, $gitattributes, '.gitattributes should not export-ignore: ' . $needle);
    }
  }

  public static function dataProviderProcessRemovesGitattributes(): \Iterator {
    yield 'phpcs' => [['phpcs'], ['phpcs.xml']];
    yield 'phpstan' => [['phpstan'], ['phpstan.neon']];
    yield 'rector' => [['rector'], ['rector.php']];
    yield 'twigcs' => [['twigcs'], ['.twig-cs-fixer.php']];
    yield 'eslint' => [['eslint'], ['.eslintrc.json', '.eslintignore', '.prettierrc.json', '.prettierignore']];
    yield 'stylelint' => [['stylelint'], ['.stylelintrc.js']];
    yield 'cspell' => [['cspell'], ['.cspell.json']];
    yield 'jest' => [['jest'], ['jest.config.js']];
    yield 'phpunit' => [['phpunit'], ['phpunit.xml', 'phpunit.d10.xml']];
    yield 'renovate' => [['renovate'], ['renovate.json']];
    yield 'all npm tools' => [['eslint', 'stylelint', 'cspell', 'jest'], ['.skip_npm_build', 'package-lock.json', 'package.json']];
  }

  /**
   * The npm manifests and the Node.js lint setup stay while any npm tool does.
   *
   * @param array<string> $tools_remove
   */
  #[DataProvider('dataProviderProcessRemovesNpm')]
  public function testProcessRemovesNpm(array $tools_remove, bool $expect_npm): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy', 'makefile'], $tools_remove, FALSE, FALSE);

    $workflow = (string) file_get_contents(self::$sut . '/.github/workflows/test.yml');
    $gitattributes = (string) file_get_contents(self::$sut . '/.gitattributes');
    $editorconfig = (string) file_get_contents(self::$sut . '/.editorconfig');

    $this->assertSame($expect_npm, file_exists(self::$sut . '/package.json'), 'package.json presence mismatch.');
    $this->assertSame($expect_npm, file_exists(self::$sut . '/package-lock.json'), 'package-lock.json presence mismatch.');
    $this->assertSame($expect_npm, str_contains($workflow, '- name: Setup Node.js'), 'test.yml Node.js setup presence mismatch.');
    $this->assertSame($expect_npm, str_contains($workflow, 'run: npm ci --ignore-scripts'), 'test.yml npm install presence mismatch.');
    $this->assertSame($expect_npm, preg_match('/^package\.json +export-ignore$/m', $gitattributes) === 1, '.gitattributes package.json entry presence mismatch.');
    $this->assertSame($expect_npm, str_contains($editorconfig, '[package.json]'), '.editorconfig package.json section presence mismatch.');
    $this->assertStringNotContainsString("\n\n\n", $editorconfig, '.editorconfig holds a double blank line.');
    $this->assertStringNotContainsString('#;', $workflow . $gitattributes . $editorconfig, 'A file keeps a marker line.');
  }

  public static function dataProviderProcessRemovesNpm(): \Iterator {
    yield 'every npm tool' => [['eslint', 'stylelint', 'cspell', 'jest'], FALSE];
    yield 'every tool' => [['phpcs', 'phpstan', 'rector', 'twigcs', 'eslint', 'stylelint', 'cspell', 'jest', 'phpunit', 'functional_javascript', 'renovate'], FALSE];
    yield 'all npm tools but ESLint' => [['stylelint', 'cspell', 'jest'], TRUE];
    yield 'all npm tools but Stylelint' => [['eslint', 'cspell', 'jest'], TRUE];
    yield 'all npm tools but CSpell' => [['eslint', 'stylelint', 'jest'], TRUE];
    yield 'all npm tools but Jest' => [['eslint', 'stylelint', 'cspell'], TRUE];
    yield 'ESLint only' => [['eslint'], TRUE];
    yield 'no npm tool' => [['phpcs', 'phpstan'], TRUE];
    yield 'no tool' => [[], TRUE];
  }

  /**
   * The 'package.json' description names the extension once.
   */
  public function testProcessRendersPackageDescription(): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], [], FALSE, FALSE);

    $package = json_decode((string) file_get_contents(self::$sut . '/package.json'), TRUE);
    $this->assertIsArray($package);

    $this->assertSame('Provides my_extension functionality.', $package['description'] ?? NULL);
  }

  /**
   * Deselecting a Drupal major prunes its CI matrix corners.
   *
   * @param array<string> $drupal_versions
   * @param array<string> $expected_present
   * @param array<string> $expected_absent
   */
  #[DataProvider('dataProviderProcessPrunesDrupalVersions')]
  public function testProcessPrunesDrupalVersions(array $drupal_versions, string $ci_file, array $expected_present, array $expected_absent): void {
    process('My Extension', 'my_extension', 'module', $drupal_versions, ['ahoy'], [], FALSE, FALSE);

    $content = (string) file_get_contents(self::$sut . '/' . $ci_file);

    foreach ($expected_present as $needle) {
      $this->assertStringContainsString($needle, $content, $ci_file . ' should contain corner: ' . $needle);
    }

    foreach ($expected_absent as $needle) {
      $this->assertStringNotContainsString($needle, $content, $ci_file . ' should not contain corner: ' . $needle);
    }

    $this->assertStringNotContainsString('#;', $content, $ci_file . ' should not retain special-comment markers.');
  }

  public static function dataProviderProcessPrunesDrupalVersions(): \Iterator {
    $d10 = ['lint-d10', 'test-php-min-d10-stable', 'test-php-max-d10-stable'];
    $d11 = ['lint-d11', 'test-php-min-d11-stable', 'test-php-max-d11-stable', 'test-php-min-d11-legacy', 'test-php-max-d11-canary'];
    $d12 = ['lint-d12', 'test-php-min-d12-stable', 'test-php-max-d12-stable', 'test-php-min-d12-legacy', 'test-php-max-d12-canary'];

    $ci_file = '.github/workflows/test.yml';

    yield 'all majors' => [['10', '11', '12'], $ci_file, array_merge($d10, $d11, $d12), []];
    yield 'd10 and d11' => [['10', '11'], $ci_file, array_merge($d10, $d11), $d12];
    yield 'd11 and d12' => [['11', '12'], $ci_file, array_merge($d11, $d12), $d10];
    yield 'd10 only' => [['10'], $ci_file, $d10, array_merge($d11, $d12)];
    yield 'd11 only' => [['11'], $ci_file, $d11, array_merge($d10, $d12)];
    yield 'd12 only' => [['12'], $ci_file, $d12, array_merge($d10, $d11)];
  }

  /**
   * Deselecting a Drupal major drops its README badge.
   *
   * @param array<string> $drupal_versions
   * @param array<string> $expected_present
   * @param array<string> $expected_absent
   */
  #[DataProvider('dataProviderProcessPrunesDrupalBadges')]
  public function testProcessPrunesDrupalBadges(array $drupal_versions, array $expected_present, array $expected_absent): void {
    process('My Extension', 'my_extension', 'module', $drupal_versions, ['ahoy'], [], FALSE, FALSE);

    $readme = (string) file_get_contents(self::$sut . '/README.md');

    foreach ($expected_present as $badge) {
      $this->assertStringContainsString('![' . $badge . '](', $readme, 'README.md should contain badge: ' . $badge);
    }

    foreach ($expected_absent as $badge) {
      $this->assertStringNotContainsString('![' . $badge . '](', $readme, 'README.md should not contain badge: ' . $badge);
    }

    $this->assertStringNotContainsString('#;', $readme, 'README.md should not retain special-comment markers.');
  }

  public static function dataProviderProcessPrunesDrupalBadges(): \Iterator {
    yield 'all majors' => [['10', '11', '12'], ['Drupal 10', 'Drupal 11', 'Drupal 12'], []];
    yield 'd10 and d11' => [['10', '11'], ['Drupal 10', 'Drupal 11'], ['Drupal 12']];
    yield 'd11 only' => [['11'], ['Drupal 11'], ['Drupal 10', 'Drupal 12']];
    yield 'd12 only' => [['12'], ['Drupal 12'], ['Drupal 10', 'Drupal 11']];
  }

  /**
   * The text that names the Drupal majors follows the selection.
   *
   * @param array<string> $drupal_versions
   * @param array<string> $expected_matrix
   */
  #[DataProvider('dataProviderProcessRendersDrupalVersionText')]
  public function testProcessRendersDrupalVersionText(array $drupal_versions, string $expected_requirement, string $expected_phpunit_comment, array $expected_matrix): void {
    process('My Extension', 'my_extension', 'module', $drupal_versions, ['ahoy'], [], FALSE, FALSE);

    $info = (string) file_get_contents(self::$sut . '/my_extension.info.yml');
    $this->assertSame([$expected_requirement], array_values(preg_grep('/^core_version_requirement:/', explode("\n", $info)) ?: []), 'my_extension.info.yml core_version_requirement mismatch.');

    $phpunit = explode("\n", (string) file_get_contents(self::$sut . '/phpunit.xml'));
    $this->assertSame($expected_phpunit_comment, $phpunit[1], 'phpunit.xml opening comment mismatch.');

    preg_match_all('/^  - (Drupal \d+ on PHP .+)$/m', (string) file_get_contents(self::$sut . '/AGENTS.md'), $matches);
    $this->assertSame($expected_matrix, $matches[1], 'AGENTS.md matrix mismatch.');
  }

  public static function dataProviderProcessRendersDrupalVersionText(): \Iterator {
    $d10 = 'Drupal 10 on PHP 8.3-8.4';
    $d11 = 'Drupal 11 on PHP 8.3-8.5';
    $d12 = 'Drupal 12 on PHP 8.5';

    yield 'all majors' => [['10', '11', '12'], 'core_version_requirement: ^10 || ^11 || ^12', '<!-- Drupal 11 and 12 PHPUnit configuration. -->', [$d10, $d11, $d12]];
    yield 'd10 and d11' => [['10', '11'], 'core_version_requirement: ^10 || ^11', '<!-- Drupal 11 PHPUnit configuration. -->', [$d10, $d11]];
    yield 'd10 and d12' => [['10', '12'], 'core_version_requirement: ^10 || ^12', '<!-- Drupal 12 PHPUnit configuration. -->', [$d10, $d12]];
    yield 'd11 and d12' => [['11', '12'], 'core_version_requirement: ^11 || ^12', '<!-- Drupal 11 and 12 PHPUnit configuration. -->', [$d11, $d12]];
    yield 'd10 only' => [['10'], 'core_version_requirement: ^10', '<!-- PHPUnit configuration. -->', [$d10]];
    yield 'd11 only' => [['11'], 'core_version_requirement: ^11', '<!-- Drupal 11 PHPUnit configuration. -->', [$d11]];
    yield 'd12 only' => [['12'], 'core_version_requirement: ^12', '<!-- Drupal 12 PHPUnit configuration. -->', [$d12]];
    yield 'unordered selection' => [['12', '10'], 'core_version_requirement: ^10 || ^12', '<!-- Drupal 12 PHPUnit configuration. -->', [$d10, $d12]];
  }

  /**
   * The local build default is the highest selected major with a stable
   * release.
   *
   * @param array<string> $drupal_versions
   */
  #[DataProvider('dataProviderProcessSetsBuildDefault')]
  public function testProcessSetsBuildDefault(array $drupal_versions, string $expected_default): void {
    process('My Extension', 'my_extension', 'module', $drupal_versions, ['ahoy'], [], FALSE, FALSE);

    $raw = (string) file_get_contents(self::$sut . '/composer.dev.json');
    $config = json_decode($raw, TRUE);
    $this->assertIsArray($config);
    /** @var array{'require-dev'?: array<string, string>, extra?: array{eddy?: array{'drupal-version'?: string}}} $config */

    $this->assertSame($expected_default, $config['extra']['eddy']['drupal-version'] ?? NULL);
    $this->assertArrayHasKey('drevops/eddy-tooling', $config['require-dev'] ?? [], 'The tooling constraint is kept.');
    $this->assertStringContainsString('"patches": {}', $raw, 'The empty patches map stays a JSON object.');
  }

  public static function dataProviderProcessSetsBuildDefault(): \Iterator {
    yield 'd10 and d11 keep 11' => [['10', '11'], '11'];
    yield 'd11 only keeps 11' => [['11'], '11'];
    yield 'd10 only narrows to 10' => [['10'], '10'];
    yield 'd12 only moves to 12' => [['12'], '12'];
    yield 'd11 and d12 keep 11' => [['11', '12'], '11'];
    yield 'all majors keep 11' => [['10', '11', '12'], '11'];
    yield 'd10 and d12 narrow to 10' => [['12', '10'], '10'];
  }

  /**
   * The shipped workflows take the given branches, or a project's committed
   * branches, or '1.x'.
   *
   * @param array<int, string> $branches
   * @param array<int, string> $expected
   */
  #[DataProvider('dataProviderProcessSetsWorkflowBranches')]
  public function testProcessSetsWorkflowBranches(array $branches, ?string $committed, array $expected): void {
    $workflow = self::$sut . '/.github/workflows/test.yml';

    if ($committed !== NULL) {
      // A scaffold update leaves the template's copy in the working tree and
      // the project's copy in 'HEAD'.
      $template = (string) file_get_contents($workflow);
      file_put_contents($workflow, $committed);
      $this->git(self::$sut, ['init']);
      $this->git(self::$sut, ['config', 'user.name', 'Test User']);
      $this->git(self::$sut, ['config', 'user.email', 'test@example.com']);
      $this->git(self::$sut, ['add', '.github/workflows/test.yml']);
      $this->git(self::$sut, ['commit', '--message', 'Project workflow']);
      file_put_contents($workflow, $template);
    }

    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], [], FALSE, FALSE, $branches);

    $this->assertSame($expected, self::triggerBranches($workflow, 'push'), 'test.yml push branches mismatch.');
    $this->assertSame($expected, self::triggerBranches($workflow, 'pull_request'), 'test.yml pull_request branches mismatch.');
    $this->assertSame($expected, self::triggerBranches(self::$sut . '/.github/workflows/draft-release-notes.yml', 'push'), 'draft-release-notes.yml push branches mismatch.');
  }

  public static function dataProviderProcessSetsWorkflowBranches(): \Iterator {
    $project = "on:\n  push:\n    branches:\n      - 2.x\n";

    yield 'new project' => [[], NULL, ['1.x']];
    yield 'project branch' => [[], $project, ['2.x']];
    yield 'project branches' => [[], "on:\n  push:\n    branches:\n      - 1.x\n      - 2.x\n", ['1.x', '2.x']];
    yield 'project without a push branch filter' => [[], "on: [push, pull_request]\n", ['1.x']];
    yield 'given branches on a new project' => [['2.x', '3.x'], NULL, ['2.x', '3.x']];
    yield 'given branches over the committed ones' => [['3.x'], $project, ['3.x']];
  }

  /**
   * The README badges name the given GitHub repository, as given.
   */
  #[DataProvider('dataProviderProcessRendersGithubRepository')]
  public function testProcessRendersGithubRepository(?string $repository, string $expected): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], [], FALSE, FALSE, [], NULL, $repository);

    $readme = (string) file_get_contents(self::$sut . '/README.md');

    $this->assertStringContainsString('https://img.shields.io/github/issues/' . $expected . '.svg', $readme);
    $this->assertStringContainsString('https://github.com/' . $expected . '/actions/workflows/test.yml', $readme);
    $this->assertStringContainsString('https://codecov.io/gh/' . $expected . '/graph/badge.svg', $readme);
    $this->assertStringContainsString('https://img.shields.io/github/license/' . $expected . ')', $readme);
    $this->assertStringNotContainsString('__GITHUB_REPOSITORY__', $readme);
    $this->assertStringContainsString('[Eddy](https://github.com/drevops/eddy)', $readme);
  }

  public static function dataProviderProcessRendersGithubRepository(): \Iterator {
    yield 'no repository' => [NULL, 'my_extension/my_extension'];
    yield 'repository' => ['AlexSkrypnyk/drupal_helpers', 'AlexSkrypnyk/drupal_helpers'];
    yield 'owner spelled like a placeholder' => ['alexskrypnyk/drupal_helpers', 'alexskrypnyk/drupal_helpers'];
    yield 'name spelled like a placeholder' => ['acme/your_extension', 'acme/your_extension'];
  }

  /**
   * Without a given repository, the badges name the 'origin' remote.
   */
  #[DataProvider('dataProviderProcessReadsGithubRepositoryFromOrigin')]
  public function testProcessReadsGithubRepositoryFromOrigin(?string $repository, string $expected): void {
    $this->git(self::$sut, ['init']);
    $this->git(self::$sut, ['remote', 'add', 'origin', 'git@github.com:acme/widget.git']);

    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], [], FALSE, FALSE, [], NULL, $repository);

    $this->assertStringContainsString('https://github.com/' . $expected . '/actions/workflows/test.yml', (string) file_get_contents(self::$sut . '/README.md'));
  }

  public static function dataProviderProcessReadsGithubRepositoryFromOrigin(): \Iterator {
    yield 'origin' => [NULL, 'acme/widget'];
    yield 'given repository over the origin' => ['drevops/eddy', 'drevops/eddy'];
  }

  /**
   * The paths in the docs follow the extension type.
   */
  #[DataProvider('dataProviderProcessRendersExtensionTypeText')]
  public function testProcessRendersExtensionTypeText(string $type, string $machine_name, string $dir): void {
    process('My Extension', $machine_name, $type, ['10', '11'], ['ahoy', 'makefile'], [], FALSE, FALSE);

    $contributing = (string) file_get_contents(self::$sut . '/CONTRIBUTING.md');
    $agents = (string) file_get_contents(self::$sut . '/AGENTS.md');
    $other_dir = $dir === 'themes' ? 'modules' : 'themes';

    $this->assertStringContainsString("a test path starts at the extension's symlink in the assembled site:", $contributing);
    $this->assertStringContainsString('make test-unit web/' . $dir . '/custom/' . $machine_name . '/tests/src/Unit/MyUnitTest.php', $contributing);
    $this->assertStringContainsString('ahoy test-unit web/' . $dir . '/custom/' . $machine_name . '/tests/src/Unit/MyUnitTest.php', $contributing);
    $this->assertStringContainsString('  web/' . $dir . '/custom/' . $machine_name . '/tests/src/Unit/MyUnitTest.php', $contributing);
    $this->assertStringNotContainsString('web/' . $other_dir . '/custom/', $contributing);
    $this->assertStringContainsString('- Extension files are symlinked from root into `build/web/' . $dir . "/custom/`\n", $agents);
    $this->assertStringNotContainsString('build/web/' . $other_dir . '/custom/', $agents);
  }

  public static function dataProviderProcessRendersExtensionTypeText(): \Iterator {
    yield 'module' => ['module', 'my_extension', 'modules'];
    yield 'theme' => ['theme', 'my_theme', 'themes'];
  }

  /**
   * The template version lands next to the build default when it is known.
   */
  #[DataProvider('dataProviderProcessRecordsVersion')]
  public function testProcessRecordsVersion(?string $version): void {
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], [], FALSE, FALSE, [], $version);

    $config = json_decode((string) file_get_contents(self::$sut . '/composer.dev.json'), TRUE);
    $this->assertIsArray($config);
    /** @var array{extra?: array{eddy?: array<string, string>}} $config */

    $expected = ['drupal-version' => '11'];

    if ($version !== NULL) {
      $expected['version'] = $version;
    }

    $this->assertSame($expected, $config['extra']['eddy'] ?? NULL);
  }

  public static function dataProviderProcessRecordsVersion(): \Iterator {
    yield 'release' => ['5.0.0'];
    yield 'commit after a release' => ['5.0.0-3-g1a2b3c4'];
    yield 'unknown' => [NULL];
  }

  public function testProcessThrowsOnInvalidClaudeSettingsJson(): void {
    file_put_contents(self::$sut . '/.claude/settings.json', '{invalid json');

    $this->expectException(\JsonException::class);
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], [], FALSE, FALSE);
  }

  public function testProcessThrowsOnInvalidClaudeSettingsStructure(): void {
    file_put_contents(self::$sut . '/.claude/settings.json', '{"foo": "bar"}');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid .claude/settings.json structure.');
    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], [], FALSE, FALSE);
  }

  public function testProcessSkipsWhenClaudeSettingsMissing(): void {
    @unlink(self::$sut . '/.claude/settings.json');

    process('My Extension', 'my_extension', 'module', ['10', '11'], ['ahoy'], [], FALSE, FALSE);

    $this->assertFileExists(self::$sut . '/my_extension.info.yml');
    $this->assertFileDoesNotExist(self::$sut . '/.claude/settings.json');
  }

  /**
   * @param array<string> $argv
   */
  #[DataProvider('dataProviderMainHelp')]
  public function testMainHelp(array $argv): void {
    ob_start();
    main($argv);
    $output = (string) ob_get_clean();

    $this->assertStringContainsString('Eddy - project installation.', $output);
    $this->assertStringContainsString('Usage:', $output);
    $this->assertStringContainsString('--help', $output);
    // The interactive flow must not run when help is requested.
    $this->assertFileExists(self::$sut . '/your_extension.info.yml');
    $this->assertFileDoesNotExist(self::$sut . '/my_extension.info.yml');
  }

  public static function dataProviderMainHelp(): \Iterator {
    yield 'help' => [['install.php', 'help']];
    yield 'long flag' => [['install.php', '--help']];
    yield 'short flag h' => [['install.php', '-h']];
    yield 'short flag question' => [['install.php', '-?']];
  }

  /**
   * Read the branch filter of a workflow trigger.
   *
   * @param string $file
   *   The workflow path.
   * @param string $trigger
   *   The trigger name, such as 'push'.
   *
   * @return mixed
   *   The 'branches' value, or NULL when the trigger has none.
   */
  protected static function triggerBranches(string $file, string $trigger): mixed {
    $workflow = Yaml::parseFile($file);
    $triggers = is_array($workflow) ? $workflow['on'] ?? NULL : NULL;
    $filters = is_array($triggers) ? $triggers[$trigger] ?? NULL : NULL;

    return is_array($filters) ? $filters['branches'] ?? NULL : NULL;
  }

  /**
   * Read the 'require-dev' package names of the SUT 'composer.dev.json'.
   *
   * @return array<int, string>
   *   The package names.
   */
  protected static function composerDevPackages(): array {
    $config = json_decode((string) file_get_contents(self::$sut . '/composer.dev.json'), TRUE);
    $packages = is_array($config) ? $config['require-dev'] ?? NULL : NULL;
    self::assertIsArray($packages);

    return array_map(strval(...), array_keys($packages));
  }

  /**
   * Count the nodes that an XPath expression selects in an XML document.
   *
   * @param string $xml
   *   The XML document.
   * @param string $expression
   *   The expression.
   *
   * @return int
   *   The number of nodes.
   */
  protected static function countNodes(string $xml, string $expression): int {
    $document = new \DOMDocument();
    $document->loadXML($xml);
    $nodes = (new \DOMXPath($document))->query($expression);
    self::assertInstanceOf(\DOMNodeList::class, $nodes);

    return $nodes->length;
  }

}
