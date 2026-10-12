<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the mirror branch that the tooling publish workflow pushes to.
 *
 * The mirror's default branch follows the tooling's version, such as '1.x',
 * so a repository variable names the mirror branch for this repository's
 * default branch. Every other branch publishes under its own name.
 *
 * The step's shell is read out of the workflow and executed, so these
 * assertions cover that script, not a re-implementation.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class PublishToolingWorkflowBranchTest extends UnitTestCase {

  protected const string WORKFLOW = '.github/workflows/eddy-publish-tooling.yml';

  protected const string JOB = 'eddy-publish-tooling';

  protected const string RESOLVE_STEP = 'Resolve the mirror branch';

  protected const string PUBLISH_STEP = "Publish '.eddy/tooling/' to 'drevops/eddy-tooling'";

  protected const string DEFAULT_BRANCH = 'main';

  protected const string TOOLING_BRANCH = 'feature/1-eddy-tooling';

  #[DataProvider('dataProviderMirrorBranch')]
  public function testMirrorBranch(string $source_branch, string $variable, string $expected): void {
    $output = $this->runStep([
      'SOURCE_BRANCH' => $source_branch,
      'DEFAULT_BRANCH' => self::DEFAULT_BRANCH,
      'EDDY_TOOLING_BRANCH' => $variable,
    ]);

    $this->assertSame('branch=' . $expected . "\n", $output);
  }

  public static function dataProviderMirrorBranch(): \Iterator {
    yield 'default branch with the variable' => [self::DEFAULT_BRANCH, '1.x', '1.x'];
    yield 'default branch without the variable' => [self::DEFAULT_BRANCH, '', self::DEFAULT_BRANCH];
    yield 'tooling branch with the variable' => [self::TOOLING_BRANCH, '1.x', self::TOOLING_BRANCH];
    yield 'tooling branch without the variable' => [self::TOOLING_BRANCH, '', self::TOOLING_BRANCH];
  }

  public function testPublishReadsResolvedBranch(): void {
    $id = self::step(self::RESOLVE_STEP)['id'] ?? NULL;
    $this->assertIsString($id, sprintf('The "%s" step needs an id for its output to be read.', self::RESOLVE_STEP));

    $env = self::step(self::PUBLISH_STEP)['env'] ?? NULL;
    $this->assertIsArray($env, sprintf('The "%s" step defines no environment.', self::PUBLISH_STEP));

    $this->assertSame(sprintf('${{ steps.%s.outputs.branch }}', $id), $env['BRANCH'] ?? NULL, sprintf('The "%s" step must push to the branch that "%s" resolves.', self::PUBLISH_STEP, self::RESOLVE_STEP));
  }

  public function testStepTakesEveryValueFromEnvironment(): void {
    $this->assertStringNotContainsString('${{', self::script(), sprintf('The "%s" step must read every value through `env:` so that its shell can be executed as it stands.', self::RESOLVE_STEP));
  }

  /**
   * Run the resolve step.
   *
   * @param array<string, string> $environment
   *   Environment the workflow defines for the step.
   *
   * @return string
   *   The outputs the step wrote.
   */
  protected function runStep(array $environment): string {
    $script = self::$tmp . '/step.sh';
    $outputs = self::$tmp . '/outputs.txt';
    file_put_contents($script, self::script());
    file_put_contents($outputs, '');

    // GitHub Actions runs a `run` block as `bash -e {0}`.
    $process = new Process(['bash', '-e', $script], self::$tmp, $environment + ['GITHUB_OUTPUT' => $outputs]);
    $process->run();

    if (!$process->isSuccessful()) {
      self::fail(sprintf("The \"%s\" step failed:\n%s", self::RESOLVE_STEP, $process->getErrorOutput()));
    }

    return (string) file_get_contents($outputs);
  }

  /**
   * Read the shell of the resolve step out of the workflow.
   *
   * @return string
   *   The step's `run` script.
   */
  protected static function script(): string {
    $script = self::step(self::RESOLVE_STEP)['run'] ?? NULL;

    if (!is_string($script)) {
      self::fail(sprintf('The "%s" step of %s runs no script.', self::RESOLVE_STEP, self::WORKFLOW));
    }

    return $script;
  }

  /**
   * Read a named step of the publish job.
   *
   * @param string $name
   *   Name of the step.
   *
   * @return array<mixed, mixed>
   *   The step definition.
   */
  protected static function step(string $name): array {
    $path = dirname(__DIR__, 4) . '/' . self::WORKFLOW;
    $contents = file_get_contents($path);

    if ($contents === FALSE) {
      self::fail(sprintf('Unable to read %s.', $path));
    }

    $parsed = Yaml::parse($contents);
    $jobs = is_array($parsed) ? $parsed['jobs'] ?? NULL : NULL;
    $job = is_array($jobs) ? $jobs[self::JOB] ?? NULL : NULL;
    $steps = is_array($job) ? $job['steps'] ?? NULL : NULL;

    foreach (is_array($steps) ? $steps : [] as $step) {
      if (is_array($step) && ($step['name'] ?? NULL) === $name) {
        return $step;
      }
    }

    self::fail(sprintf('The %s job of %s has no "%s" step.', self::JOB, self::WORKFLOW, $name));
  }

}
