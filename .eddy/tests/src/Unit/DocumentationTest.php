<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that documentation stays consistent with the files it describes.
 *
 * A script can be added to the tooling package without a 'bin' entry, which
 * leaves it uninstalled, or without a row in the package README table.
 * A path can be copied into a PHPUnit config without being rebased onto the
 * build root. The migration guide repeats the update skill commands from
 * 'AGENTS.md', so either copy can change alone. The update skill answers every
 * prompt of 'install.php' through a variable and detects every tool it
 * offers, so a prompt or a tool can be added to 'install.php' alone.
 *
 * No such drift is reported by a linter, because each file stays individually
 * valid while the files disagree.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class DocumentationTest extends UnitTestCase {

  #[DataProvider('dataProviderToolingCommandIsDocumented')]
  public function testToolingCommandIsDocumented(string $name): void {
    $readme = file_get_contents(self::toolingDir() . '/README.md');
    $this->assertIsString($readme);

    // Anchored to the entry column: a name mentioned in another row's
    // description is a cross-reference, not an entry of its own.
    $documented = preg_match('/^\|\s*`' . preg_quote($name, '/') . '`\s*\|/m', $readme) === 1;

    $this->assertTrue($documented, sprintf('The tooling README has no table row for `%s`.', $name));
  }

  public static function dataProviderToolingCommandIsDocumented(): \Iterator {
    yield from self::toolingCommands();
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

  #[DataProvider('dataProviderMigrationGuideFetchesUpdateSkill')]
  public function testMigrationGuideFetchesUpdateSkill(string $line): void {
    $guide = file_get_contents(self::rootDir() . '/MIGRATION.md');
    $this->assertIsString($guide);

    // Compared line by line, so the list item that holds a command block can
    // indent it differently.
    $lines = array_map(trim(...), explode("\n", $guide));

    $this->assertContains($line, $lines, sprintf('MIGRATION.md does not fetch the update skill the way AGENTS.md does: `%s` is missing.', $line));
  }

  public static function dataProviderMigrationGuideFetchesUpdateSkill(): \Iterator {
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

  protected static function updateSkillPath(): string {
    return self::rootDir() . '/.eddy/skills/update-consumer-eddy/SKILL.md';
  }

  protected static function rootDir(): string {
    return dirname(__DIR__, 4);
  }

  protected static function toolingDir(): string {
    return self::rootDir() . '/.eddy/tooling';
  }

}
