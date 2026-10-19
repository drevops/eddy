<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use DrevOps\Eddy\Tests\Traits\GitTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;

/**
 * Tests the search the update skill runs for names of the example extension.
 *
 * The search lists files that a project can lack, such as 'package.json' in a
 * project without Node tooling.
 */
#[Group('p0')]
final class UpdateSkillRepointSearchTest extends UnitTestCase {

  use GitTrait;

  /**
   * @param array<string, string> $files
   *   The content of each file of the project, keyed by path.
   * @param array<int, string> $expected_files
   *   The files the search reports, sorted.
   */
  #[DataProvider('dataProviderRepointSearch')]
  public function testRepointSearch(array $files, int $expected_exit_code, array $expected_files): void {
    $project = self::$tmp . '/project';

    foreach ($files as $path => $content) {
      File::dump($project . '/' . $path, $content);
    }

    self::gitInit($project);
    self::gitRun($project, ['add', '--force', '--', ...array_keys($files)]);

    $process = Process::fromShellCommandline(self::repointSearchCommand(), $project, self::gitEnvironment());
    $process->run();

    $this->assertSame($expected_exit_code, $process->getExitCode(), $process->getErrorOutput());
    $this->assertSame($expected_files, self::reportedFiles($process->getOutput()));
  }

  public static function dataProviderRepointSearch(): \Iterator {
    $docs = [
      'CONTRIBUTING.md' => "# Contributing\n",
      'AGENTS.md' => "# AGENTS.md\n",
      'README.md' => "# Force Crystal\n",
    ];
    $node = [
      'package.json' => "{\"scripts\": {\"lint\": \"eslint js\"}}\n",
      'jest.config.js' => "module.exports = {};\n",
      '.stylelintrc.js' => "module.exports = {};\n",
    ];

    yield 'reference, no Node config files' => [
      ['CONTRIBUTING.md' => "Extend `ForceCrystalFunctionalJavascriptTestBase`.\n"] + $docs,
      0,
      ['CONTRIBUTING.md'],
    ];
    yield 'no reference, no Node config files' => [$docs, 1, []];
    yield 'example script name, no Node config files' => [
      ['README.md' => "Run `scripts/provision-example.sh` once.\n"] + $docs,
      0,
      ['README.md'],
    ];
    yield 'references in Node config files' => [
      [
        'package.json' => "{\"scripts\": {\"lint-css\": \"stylelint css/force_crystal.css\"}}\n",
        'jest.config.js' => "module.exports = {testMatch: ['js/force_crystal.test.js']};\n",
        '.stylelintrc.js' => "// Lints force_crystal.css.\nmodule.exports = {};\n",
      ] + $docs,
      0,
      ['.stylelintrc.js', 'jest.config.js', 'package.json'],
    ];
    yield 'no reference, Node config files' => [$docs + $node, 1, []];
  }

  /**
   * Read the search command from the update skill.
   *
   * @return string
   *   The command, with the placeholders replaced by the names of a project.
   */
  protected static function repointSearchCommand(): string {
    $skill = File::read(self::rootDir() . '/.eddy/skills/update-consumer-eddy/SKILL.md');

    preg_match('/^## Step \d+: Repoint references to the example extension\n.*?^```bash\n(.*?)\n```/ms', $skill, $matches);
    $command = $matches[1] ?? '';

    self::assertNotSame('', $command, 'The update skill has no command in its step that repoints references to the example extension.');

    return str_replace(['<MachineName>', '<machine_name>'], ['ForceCrystal', 'force_crystal'], $command);
  }

  /**
   * List the files that lines of search output name.
   *
   * @return array<int, string>
   *   The paths, sorted.
   */
  protected static function reportedFiles(string $output): array {
    $files = [];

    foreach (array_filter(explode("\n", $output)) as $line) {
      $files[] = explode(':', $line, 2)[0];
    }

    $files = array_values(array_unique($files));
    sort($files);

    return $files;
  }

}
