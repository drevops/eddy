<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests that documentation stays consistent with the files it describes.
 *
 * A script added to the tooling package can lack a 'bin' entry, which leaves
 * it uninstalled, or a row in the package README and the documentation site.
 * A path can be copied into a PHPUnit config without being rebased onto the
 * build root.
 *
 * The upgrade guide repeats the update skill commands from 'AGENTS.md', so
 * either copy can change alone.
 *
 * The update skill answers every 'install.php' prompt through a variable and
 * detects every tool it offers, so either can be added to 'install.php' alone.
 *
 * The documentation site lists the CI jobs, the ignore-failure toggles and the
 * variables that the workflows and the tooling read, so any of them can
 * change without the page that lists it.
 *
 * No such drift is reported by a linter, because each file stays individually
 * valid while the files disagree.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class DocumentationTest extends UnitTestCase {

  /**
   * Variables that the shell or the SSH agent set, not the project.
   */
  protected const array PROCESS_VARIABLES = ['HOME', 'SSH_AGENT_PID', 'TERM'];

  #[DataProvider('dataProviderToolingCommandIsDocumented')]
  public function testToolingCommandIsDocumented(string $name, string $path): void {
    $document = file_get_contents($path);
    $this->assertIsString($document);

    // Anchored to the entry column: a name mentioned in another row's
    // description is a cross-reference, not an entry of its own.
    $documented = preg_match('/^\|\s*`' . preg_quote($name, '/') . '`\s*\|/m', $document) === 1;

    $this->assertTrue($documented, sprintf('%s has no table row for `%s`.', self::relativePath($path), $name));
  }

  public static function dataProviderToolingCommandIsDocumented(): \Iterator {
    $documents = [self::toolingDir() . '/README.md', self::docsDir() . '/development/tooling.mdx'];

    foreach (self::toolingCommands() as $name => $command) {
      foreach ($documents as $path) {
        yield $name . ' in ' . self::relativePath($path) => ['name' => $command['name'], 'path' => $path];
      }
    }
  }

  #[DataProvider('dataProviderToolingCommandExists')]
  public function testToolingCommandExists(string $name): void {
    $this->assertFileExists(self::toolingDir() . '/src/' . $name, sprintf('The tooling composer.json declares the bin `%s`, which does not exist.', $name));
  }

  public static function dataProviderToolingCommandExists(): \Iterator {
    yield from self::toolingCommands();
  }

  #[DataProvider('dataProviderToolingScriptIsDeclared')]
  public function testToolingScriptIsDeclared(string $name): void {
    $names = array_column(iterator_to_array(self::toolingCommands()), 'name');

    $this->assertContains($name, $names, sprintf('The tooling composer.json does not declare `src/%s` as a bin, so Composer does not install it.', $name));
  }

  public static function dataProviderToolingScriptIsDeclared(): \Iterator {
    $paths = glob(self::toolingDir() . '/src/*') ?: [];

    self::assertNotSame([], $paths, 'No scripts found in the tooling package.');

    foreach ($paths as $path) {
      $name = basename($path);

      if ($name === 'helpers.php') {
        continue;
      }

      yield $name => ['name' => $name];
    }
  }

  #[DataProvider('dataProviderPhpunitCorePathsUseDocrootPrefix')]
  public function testPhpunitCorePathsUseDocrootPrefix(string $path): void {
    $contents = file_get_contents($path);
    $this->assertIsString($contents);

    // These configs are run from the build root, not the Drupal root, so the
    // docroot prefix in `bootstrap` applies to every other core-relative path.
    // Paths inside comments are included.
    preg_match('/bootstrap="([^"]+)"/', $contents, $bootstrap);
    $bootstrap_path = $bootstrap[1] ?? '';

    $this->assertNotSame('', $bootstrap_path, sprintf('%s declares no bootstrap attribute.', basename($path)));

    $position = strpos($bootstrap_path, 'core/');
    $this->assertIsInt($position, sprintf('%s bootstraps outside core: %s', basename($path), $bootstrap_path));

    $prefix = substr($bootstrap_path, 0, $position);

    $unprefixed = [];

    foreach (explode("\n", $contents) as $index => $line) {
      // Match each reference together with the path segments leading into it,
      // so the prefix can be compared by value. A lookbehind cannot serve
      // here: PCRE requires a fixed-length one, and the prefix is derived.
      preg_match_all('/[A-Za-z0-9_.\/-]*\bcore\//', $line, $references);

      $offenders = array_filter($references[0], static fn(string $reference): bool => !str_starts_with($reference, $prefix));

      foreach ($offenders as $offender) {
        $unprefixed[] = sprintf('line %d: %s', $index + 1, $offender);
      }
    }

    $this->assertSame([], $unprefixed, sprintf('%s references core paths without the `%s` docroot prefix.', basename($path), $prefix));
  }

  public static function dataProviderPhpunitCorePathsUseDocrootPrefix(): \Iterator {
    $paths = glob(self::rootDir() . '/phpunit*.xml') ?: [];

    self::assertNotSame([], $paths, 'No PHPUnit configuration files found in the project root.');

    foreach ($paths as $path) {
      yield basename($path) => ['path' => $path];
    }
  }

  #[DataProvider('dataProviderUpgradeGuideFetchesUpdateSkill')]
  public function testUpgradeGuideFetchesUpdateSkill(string $line): void {
    $guide = file_get_contents(self::upgradeGuidePath());
    $this->assertIsString($guide);

    // Compared line by line, so the list item that holds a command block can
    // indent it differently.
    $lines = array_map(trim(...), explode("\n", $guide));

    $this->assertContains($line, $lines, sprintf('The upgrade guide does not fetch the update skill the way AGENTS.md does: `%s` is missing.', $line));
  }

  public static function dataProviderUpgradeGuideFetchesUpdateSkill(): \Iterator {
    $agents = (string) file_get_contents(self::rootDir() . '/AGENTS.md');

    preg_match('/^## Updating the scaffold\n(.*?)(?=^## |\z)/ms', $agents, $matches);
    $section = $matches[1] ?? '';

    self::assertNotSame('', $section, 'AGENTS.md has no "Updating the scaffold" section.');

    preg_match_all('/```bash\n(.*?)```/s', $section, $blocks);

    self::assertNotSame([], $blocks[1], 'The "Updating the scaffold" section of AGENTS.md has no bash blocks.');

    foreach ($blocks[1] as $block) {
      foreach (explode("\n", $block) as $line) {
        $line = trim($line);

        if ($line !== '') {
          yield $line => ['line' => $line];
        }
      }
    }
  }

  #[DataProvider('dataProviderUpdateSkillAnswersInstallPrompt')]
  public function testUpdateSkillAnswersInstallPrompt(string $variable): void {
    $this->assertContains($variable, self::updateSkillInstallVariables(), sprintf('The update skill runs install.php without %s, so that prompt waits for input that never comes.', $variable));
  }

  public static function dataProviderUpdateSkillAnswersInstallPrompt(): \Iterator {
    foreach (self::installPromptVariables() as $variable) {
      yield $variable => ['variable' => $variable];
    }
  }

  #[DataProvider('dataProviderUpdateSkillVariableIsInstallVariable')]
  public function testUpdateSkillVariableIsInstallVariable(string $variable): void {
    $this->assertContains($variable, self::installVariables(), sprintf('The update skill sets %s, which install.php does not read.', $variable));
  }

  public static function dataProviderUpdateSkillVariableIsInstallVariable(): \Iterator {
    foreach (self::updateSkillInstallVariables() as $variable) {
      yield $variable => ['variable' => $variable];
    }
  }

  #[DataProvider('dataProviderToolingPhpFloorIsDocumented')]
  public function testToolingPhpFloorIsDocumented(string $path): void {
    $manifest = json_decode((string) file_get_contents(self::toolingDir() . '/composer.json'), TRUE);
    $requirement = is_array($manifest) && is_array($manifest['require'] ?? NULL) ? ($manifest['require']['php'] ?? NULL) : NULL;

    $this->assertIsString($requirement);
    $this->assertMatchesRegularExpression('/^>=\d+\.\d+$/', $requirement, 'The tooling package states its PHP requirement in a form other than ">=X.Y", so the documented floor cannot be checked.');

    $phrase = 'requires PHP ' . substr($requirement, 2) . ' or newer';

    $this->assertStringContainsString($phrase, (string) file_get_contents($path), sprintf('%s does not state the PHP floor of the tooling package: "%s" is missing.', basename($path), $phrase));
  }

  public static function dataProviderToolingPhpFloorIsDocumented(): \Iterator {
    yield 'update skill' => ['path' => self::updateSkillPath()];
    yield 'upgrade guide' => ['path' => self::upgradeGuidePath()];
    yield 'tooling README' => ['path' => self::toolingDir() . '/README.md'];
    yield 'tooling page' => ['path' => self::docsDir() . '/development/tooling.mdx'];
  }

  #[DataProvider('dataProviderCiJobIsDocumented')]
  public function testCiJobIsDocumented(string $name, string $php, string $drupal): void {
    $page = (string) file_get_contents(self::docsDir() . '/continuous-integration/README.mdx');

    $row = '/^\|\s*`' . preg_quote($name, '/') . '`\s*\|\s*`' . preg_quote($php, '/') . '`\s*\|\s*`' . preg_quote($drupal, '/') . '`\s*\|/m';

    $this->assertTrue(preg_match($row, $page) === 1, sprintf('The CI page has no row for `%s` on PHP %s and Drupal %s.', $name, $php, $drupal));
  }

  public static function dataProviderCiJobIsDocumented(): \Iterator {
    foreach (self::ciJobs() as $job) {
      yield $job['name'] => $job;
    }
  }

  #[DataProvider('dataProviderRequiredJobIsDocumented')]
  public function testRequiredJobIsDocumented(string $name): void {
    $page = (string) file_get_contents(self::docsDir() . '/continuous-integration/branch-protection.mdx');

    $this->assertTrue(str_contains($page, '`' . $name . '`'), sprintf('The branch protection page does not list the `%s` job.', $name));
  }

  public static function dataProviderRequiredJobIsDocumented(): \Iterator {
    foreach (self::ciJobs() as $job) {
      yield $job['name'] => ['name' => $job['name']];
    }
  }

  #[DataProvider('dataProviderIgnoreFailureVariableIsDocumented')]
  public function testIgnoreFailureVariableIsDocumented(string $variable): void {
    $page = (string) file_get_contents(self::docsDir() . '/continuous-integration/configuration.mdx');

    $row = '/^\|\s*`' . preg_quote($variable, '/') . '`\s*\|/m';

    $this->assertTrue(preg_match($row, $page) === 1, sprintf('The CI configuration page has no row for `%s`.', $variable));
  }

  public static function dataProviderIgnoreFailureVariableIsDocumented(): \Iterator {
    $workflow = (string) file_get_contents(self::rootDir() . '/.github/workflows/test.yml');

    preg_match_all('/\bvars\.(CI_\w+_IGNORE_FAILURE)\b/', $workflow, $matches);
    $variables = array_values(array_unique($matches[1]));

    self::assertNotSame([], $variables, 'test.yml has no ignore-failure toggles.');

    foreach ($variables as $variable) {
      yield $variable => ['variable' => $variable];
    }
  }

  #[DataProvider('dataProviderToolingVariableIsDocumented')]
  public function testToolingVariableIsDocumented(string $variable): void {
    $page = (string) file_get_contents(self::docsDir() . '/development/environment-variables.mdx');

    $row = '/^\|\s*`' . preg_quote($variable, '/') . '`\s*\|/m';

    $this->assertTrue(preg_match($row, $page) === 1, sprintf('The environment variables page has no row for `%s`, which the tooling reads.', $variable));
  }

  public static function dataProviderToolingVariableIsDocumented(): \Iterator {
    $variables = [];

    foreach (glob(self::toolingDir() . '/src/*') ?: [] as $path) {
      preg_match_all("/\b(?:getenv|getenv_default|getenv_required|resolve_env_value|resolve_port_value)\('([A-Z][A-Z0-9_]*)'/", (string) file_get_contents($path), $matches);
      $variables = array_merge($variables, $matches[1]);
    }

    $variables = array_values(array_diff(array_unique($variables), self::PROCESS_VARIABLES));
    sort($variables);

    self::assertNotSame([], $variables, 'The tooling reads no environment variables.');

    foreach ($variables as $variable) {
      yield $variable => ['variable' => $variable];
    }
  }

  #[DataProvider('dataProviderUpdateSkillDetectsTool')]
  public function testUpdateSkillDetectsTool(string $tool): void {
    $skill = (string) file_get_contents(self::updateSkillPath());

    // The detection list follows each tool with the file that marks it, such
    // as '`phpcs` (`phpcs.xml`)'.
    $detected = str_contains($skill, '`' . $tool . '` (');

    $this->assertTrue($detected, sprintf('The update skill does not detect the %s tool, so it removes the tool from every project it updates.', $tool));
  }

  public static function dataProviderUpdateSkillDetectsTool(): \Iterator {
    $install = (string) file_get_contents(self::rootDir() . '/install.php');

    preg_match('/\$tool_options = \[(.*?)\];/s', $install, $options);
    preg_match_all("/'(\w+)' =>/", $options[1] ?? '', $tools);

    self::assertNotSame([], $tools[1], 'install.php declares no tool options.');

    foreach ($tools[1] as $tool) {
      yield $tool => ['tool' => $tool];
    }
  }

  /**
   * List the commands the tooling package installs.
   *
   * @return \Iterator<string, array{name: string}>
   *   The command names, keyed by name.
   */
  protected static function toolingCommands(): \Iterator {
    $manifest = json_decode((string) file_get_contents(self::toolingDir() . '/composer.json'), TRUE);
    $bins = is_array($manifest) && is_array($manifest['bin'] ?? NULL) ? $manifest['bin'] : [];

    self::assertNotSame([], $bins, 'The tooling composer.json declares no bins.');

    foreach ($bins as $bin) {
      self::assertIsString($bin);
      $name = basename($bin);

      yield $name => ['name' => $name];
    }
  }

  /**
   * List the variables that answer the prompts of 'install.php'.
   *
   * @return array<int, string>
   *   The variable names, such as 'INSTALL_NAME'.
   */
  protected static function installPromptVariables(): array {
    $install = (string) file_get_contents(self::rootDir() . '/install.php');

    preg_match("/env_prefix: '(\w+)'/", $install, $matches);
    $prefix = $matches[1] ?? '';

    self::assertNotSame('', $prefix, 'install.php sets no prefix for its prompt variables.');

    preg_match_all("/^ +'(\w+)' => Prompty::/m", $install, $keys);

    self::assertNotSame([], $keys[1], 'install.php declares no prompts.');

    // Prompty reads the answer to each step from the variable named by the
    // prefix and the step key in upper case.
    return array_map(static fn(string $key): string => $prefix . strtoupper($key), $keys[1]);
  }

  /**
   * List the variables that 'install.php' reads.
   *
   * @return array<int, string>
   *   The variable names: those that answer the prompts, then those that
   *   'install.php' reads with 'getenv()'.
   */
  protected static function installVariables(): array {
    $install = (string) file_get_contents(self::rootDir() . '/install.php');

    preg_match_all("/getenv\('(INSTALL_\w+)'\)/", $install, $matches);

    return array_values(array_unique([...self::installPromptVariables(), ...$matches[1]]));
  }

  /**
   * List the variables the update skill sets for 'install.php'.
   *
   * @return array<int, string>
   *   The variable names, in order of appearance.
   */
  protected static function updateSkillInstallVariables(): array {
    $skill = (string) file_get_contents(self::updateSkillPath());

    // A list item can indent the command block that holds the assignments.
    preg_match_all('/^[ \t]*(INSTALL_\w+)=/m', $skill, $matches);

    self::assertNotSame([], $matches[1], 'The update skill sets no INSTALL_ variables.');

    return array_values(array_unique($matches[1]));
  }

  /**
   * List the jobs of the 'lint' and 'test' matrices of the 'test.yml' workflow.
   *
   * @return array<int, array{name: string, php: string, drupal: string}>
   *   The jobs in workflow order, each with its PHP and Drupal versions.
   */
  protected static function ciJobs(): array {
    $workflow = Yaml::parse((string) file_get_contents(self::rootDir() . '/.github/workflows/test.yml'));
    $jobs = [];

    foreach (['lint', 'test'] as $job_name) {
      $include = is_array($workflow) ? ($workflow['jobs'][$job_name]['strategy']['matrix']['include'] ?? NULL) : NULL;

      self::assertIsArray($include, sprintf('test.yml has no matrix for the `%s` job.', $job_name));

      foreach ($include as $leg) {
        self::assertIsArray($leg);
        self::assertIsScalar($leg['name'] ?? NULL);
        self::assertIsScalar($leg['php-version'] ?? NULL);
        self::assertIsScalar($leg['drupal-version'] ?? NULL);

        $jobs[] = [
          'name' => (string) $leg['name'],
          'php' => (string) $leg['php-version'],
          'drupal' => (string) $leg['drupal-version'],
        ];
      }
    }

    return $jobs;
  }

  protected static function updateSkillPath(): string {
    return self::rootDir() . '/.eddy/skills/update-consumer-eddy/SKILL.md';
  }

  protected static function upgradeGuidePath(): string {
    return self::docsDir() . '/updating/upgrading-from-4x.mdx';
  }

  protected static function docsDir(): string {
    return self::rootDir() . '/.eddy/docs/content';
  }

  protected static function relativePath(string $path): string {
    return substr($path, strlen(self::rootDir()) + 1);
  }

  protected static function rootDir(): string {
    return dirname(__DIR__, 4);
  }

  protected static function toolingDir(): string {
    return self::rootDir() . '/.eddy/tooling';
  }

}
