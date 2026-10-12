<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Traits\GitTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

use function eddy_skills;
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

    $package_json = (string) file_get_contents(self::$sut . '/package.json');
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
      ['.eslintrc.json', '.stylelintrc.js', '.cspell.json', 'jest.config.js', 'js/my_extension.test.js'],
      [],
      ['"eslint"', '"stylelint"', '"cspell"', '"jest"', 'devDependencies'],
      ['Running ESLint', 'NodeJS linters', 'npm run lint-spell', 'npm test'],
    ];

    // Removing PHPUnit also removes the FunctionalJavascript layer (Mink and
    // Selenium deps), per the normalisation in 'remove_tools()'.
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
      ['behat/mink', 'lullabot/mink-selenium2-driver', 'symfony/browser-kit'],
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
   */
  public function testProcessKeepsProjectFiles(): void {
    // 'process()' rewrites each of these tokens in a file it walks.
    $content = "your_extension YourExtension type: module\n#;< META\nblock\n#;> META\n";
    $paths = [
      '.claude/settings.local.json',
      '.claude/skills/update-consumer-eddy/SKILL.md',
      '.claude/skills/project-skill/SKILL.md',
      'build/web/core/modules/system/system.info.yml',
      '.logs/your_extension.log',
      '.env',
    ];

    $filesystem = new Filesystem();
    foreach ($paths as $path) {
      $filesystem->dumpFile(self::$sut . '/' . $path, $content);
    }

    process('My Theme', 'my_theme', 'theme', ['10', '11'], ['ahoy'], [], FALSE, FALSE);

    foreach ($paths as $path) {
      $this->assertSame($content, file_get_contents(self::$sut . '/' . $path), 'Expected untouched: ' . $path);
    }

    $this->assertStringContainsString('type: theme', (string) file_get_contents(self::$sut . '/my_theme.info.yml'));
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
   * Removed blocks leave no double blank line in the Markdown files.
   *
   * @param array<string> $drupal_versions
   * @param array<string> $command_wrapper
   */
  #[DataProvider('dataProviderProcessLeavesNoDoubleBlankLines')]
  public function testProcessLeavesNoDoubleBlankLines(array $drupal_versions, array $command_wrapper): void {
    process('My Extension', 'my_extension', 'module', $drupal_versions, $command_wrapper, [], FALSE, FALSE);

    foreach (['AGENTS.md', 'CONTRIBUTING.md', 'README.md'] as $file) {
      $this->assertStringNotContainsString("\n\n\n", (string) file_get_contents(self::$sut . '/' . $file), $file . ' holds a double blank line.');
    }
  }

  public static function dataProviderProcessLeavesNoDoubleBlankLines(): \Iterator {
    yield 'both wrappers' => [['10', '11'], ['ahoy', 'makefile']];
    yield 'ahoy only' => [['10', '11'], ['ahoy']];
    yield 'makefile only' => [['10', '11'], ['makefile']];
    yield 'no wrappers' => [['10', '11'], []];
    yield 'all majors' => [['10', '11', '12'], ['ahoy']];
    yield 'd10 only' => [['10'], ['ahoy']];
    yield 'd11 only' => [['11'], ['ahoy']];
    yield 'd12 only' => [['12'], ['ahoy']];
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
   * The local build default follows the highest selected major.
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
    yield 'd11 and d12 move to 12' => [['11', '12'], '12'];
    yield 'all majors move to 12' => [['10', '11', '12'], '12'];
    yield 'unordered selection uses the highest major' => [['12', '10'], '12'];
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

}
