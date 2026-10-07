<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Traits\DeployWorkflowTrait;
use DrevOps\Eddy\Tests\Traits\GitTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;

/**
 * Tests what the deploy workflow passes to the deploy script.
 *
 * A tag push reports the tag name as the head branch, so the workflow deploys
 * that tag instead of a branch. Git allows a tag to share a branch's name, so
 * the run counts as a tag push only when that tag points at the triggering
 * commit.
 *
 * A branch push deploys only while its commit is the branch tip that the
 * checkout fetched. Runs finish out of order and can be re-run, and deploying
 * an older commit would rewind the remote branch.
 *
 * The step's shell is read out of the workflow and executed against a
 * purpose-built repository, so these assertions cover that script, not a
 * re-implementation.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class DeployWorkflowBranchTest extends UnitTestCase {

  use DeployWorkflowTrait;
  use GitTrait;

  protected const string STEP = 'Deploy to Remote';

  protected const string DEFAULT_BRANCH = 'main';

  protected const string FEATURE_BRANCH = 'feature/x';

  protected const string LIGHTWEIGHT_TAG = '1.0.0';

  protected const string ANNOTATED_TAG = '2.0.0';

  /**
   * @param array<string, string>|null $expected
   */
  #[DataProvider('dataProviderDeployment')]
  public function testDeployment(string $deploy_branch, string $head_branch, string $head_commit, ?array $expected): void {
    $repository = $this->createRepository();

    $this->runStep($repository, [
      'DEPLOY_BRANCH' => $deploy_branch,
      'HEAD_BRANCH' => $head_branch,
      'HEAD_SHA' => $this->resolveCommit($repository, $head_commit),
    ]);

    $this->assertSame($expected, $this->readDeployment());
  }

  public static function dataProviderDeployment(): \Iterator {
    yield 'branch push' => [
      'deploy_branch' => '',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tip',
      'expected' => ['DEPLOY_BRANCH' => self::FEATURE_BRANCH, 'DEPLOY_TAG' => ''],
    ];

    yield 'lightweight tag push' => [
      'deploy_branch' => '',
      'head_branch' => self::LIGHTWEIGHT_TAG,
      'head_commit' => 'tagged',
      'expected' => ['DEPLOY_BRANCH' => '', 'DEPLOY_TAG' => self::LIGHTWEIGHT_TAG],
    ];

    yield 'annotated tag push' => [
      'deploy_branch' => '',
      'head_branch' => self::ANNOTATED_TAG,
      'head_commit' => 'tagged',
      'expected' => ['DEPLOY_BRANCH' => '', 'DEPLOY_TAG' => self::ANNOTATED_TAG],
    ];

    yield 'branch sharing a name with a tag on another commit' => [
      'deploy_branch' => '',
      'head_branch' => self::LIGHTWEIGHT_TAG,
      'head_commit' => 'tip',
      'expected' => ['DEPLOY_BRANCH' => self::LIGHTWEIGHT_TAG, 'DEPLOY_TAG' => ''],
    ];

    yield 'repository variable redirects a branch push' => [
      'deploy_branch' => 'custom',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tip',
      'expected' => ['DEPLOY_BRANCH' => 'custom', 'DEPLOY_TAG' => ''],
    ];

    yield 'repository variable leaves a tag push alone' => [
      'deploy_branch' => 'custom',
      'head_branch' => self::LIGHTWEIGHT_TAG,
      'head_commit' => 'tagged',
      'expected' => ['DEPLOY_BRANCH' => '', 'DEPLOY_TAG' => self::LIGHTWEIGHT_TAG],
    ];

    yield 'branch moved past the commit' => [
      'deploy_branch' => '',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tagged',
      'expected' => NULL,
    ];

    yield 'repository variable does not override a branch that moved' => [
      'deploy_branch' => 'custom',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tagged',
      'expected' => NULL,
    ];

    yield 'deleted branch' => [
      'deploy_branch' => '',
      'head_branch' => 'deleted',
      'head_commit' => 'tip',
      'expected' => NULL,
    ];

    yield 'no head branch' => [
      'deploy_branch' => '',
      'head_branch' => '',
      'head_commit' => 'tip',
      'expected' => NULL,
    ];

    yield 'head commit absent from the clone' => [
      'deploy_branch' => '',
      'head_branch' => self::LIGHTWEIGHT_TAG,
      'head_commit' => 'unknown',
      'expected' => NULL,
    ];
  }

  public function testSkippedRunReportsNotice(): void {
    $repository = $this->createRepository();
    $head_sha = $this->resolveCommit($repository, 'tagged');

    $output = $this->runStep($repository, [
      'DEPLOY_BRANCH' => '',
      'HEAD_BRANCH' => self::FEATURE_BRANCH,
      'HEAD_SHA' => $head_sha,
    ]);

    $this->assertSame(sprintf("::notice::Skip deployment because %s is no longer the tip of %s.\n", $head_sha, self::FEATURE_BRANCH), $output);
  }

  public function testStepTakesEveryValueFromEnvironment(): void {
    $this->assertStringNotContainsString('${{', self::stepScript(), sprintf('The "%s" step must read every value through `env:` so that its shell can be executed as it stands.', self::STEP));
  }

  /**
   * Run the deploy step against a repository.
   *
   * @param string $repository
   *   Directory of the repository to run in.
   * @param array<string, string> $environment
   *   Environment the workflow defines for the step.
   *
   * @return string
   *   The standard output of the step.
   */
  protected function runStep(string $repository, array $environment): string {
    $script = self::$tmp . '/step.sh';
    file_put_contents($script, self::stepScript());

    // GitHub Actions runs a `run` block as `bash -e {0}`.
    $process = new Process(['bash', '-e', $script], $repository, $environment + self::gitEnvironment());
    $process->run();

    if (!$process->isSuccessful()) {
      self::fail(sprintf("The deploy step failed:\n%s", $process->getErrorOutput()));
    }

    return $process->getOutput();
  }

  /**
   * Read the variables the deploy script stub recorded.
   *
   * @return array<string, string>|null
   *   The recorded variables, or NULL when the deploy script did not run.
   */
  protected function readDeployment(): ?array {
    $file = self::deploymentFile();

    if (!file_exists($file)) {
      return NULL;
    }

    $deployment = [];

    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
      [$name, $value] = explode('=', $line, 2) + [1 => ''];
      $deployment[$name] = $value;
    }

    return $deployment;
  }

  /**
   * Create a clone as the workflow's checkout leaves it.
   *
   * The checkout fetches every branch as a remote-tracking ref and every tag.
   * A branch and a tag share a name, with the tag on an earlier commit.
   *
   * @return string
   *   Directory of the created repository.
   */
  protected function createRepository(): string {
    $repository = self::$tmp . '/repository';
    mkdir($repository . '/vendor/bin', 0755, TRUE);

    // The step ends by invoking the deploy command, so this stub records the
    // variables it receives.
    file_put_contents($repository . '/vendor/bin/eddy-deploy', sprintf("#!/usr/bin/env bash\nprintf 'DEPLOY_BRANCH=%%s\\nDEPLOY_TAG=%%s\\n' \"\${DEPLOY_BRANCH:-}\" \"\${DEPLOY_TAG:-}\" > %s\n", escapeshellarg(self::deploymentFile())));
    chmod($repository . '/vendor/bin/eddy-deploy', 0755);

    $this->git($repository, ['init', '--initial-branch=' . self::DEFAULT_BRANCH]);
    $this->git($repository, ['config', 'user.name', 'Test User']);
    $this->git($repository, ['config', 'user.email', 'test@example.com']);

    file_put_contents($repository . '/file.txt', 'first');
    $this->git($repository, ['add', '--all']);
    $this->git($repository, ['commit', '--message', 'First commit']);

    $this->git($repository, ['tag', self::LIGHTWEIGHT_TAG]);
    $this->git($repository, ['tag', '--annotate', self::ANNOTATED_TAG, '--message', 'Release']);

    file_put_contents($repository . '/file.txt', 'second');
    $this->git($repository, ['commit', '--all', '--message', 'Second commit']);

    foreach ([self::DEFAULT_BRANCH, self::FEATURE_BRANCH, self::LIGHTWEIGHT_TAG] as $branch) {
      $this->git($repository, ['update-ref', 'refs/remotes/origin/' . $branch, 'HEAD']);
    }

    return $repository;
  }

  /**
   * Resolve the commit that a run was triggered on.
   *
   * @param string $repository
   *   Directory of the repository to resolve in.
   * @param string $name
   *   Name of a commit of the repository built by ::createRepository().
   *
   * @return string
   *   The commit SHA.
   */
  protected function resolveCommit(string $repository, string $name): string {
    return match ($name) {
      'tagged' => $this->git($repository, ['rev-parse', self::DEFAULT_BRANCH . '~1']),
      'tip' => $this->git($repository, ['rev-parse', self::DEFAULT_BRANCH]),
      // A commit the deploy clone does not hold.
      'unknown' => str_repeat('0', 40),
      default => self::fail(sprintf('Unknown commit "%s".', $name)),
    };
  }

  /**
   * Path of the file the deploy script stub writes.
   *
   * @return string
   *   The absolute path.
   */
  protected static function deploymentFile(): string {
    return self::$tmp . '/deployment.txt';
  }

  /**
   * Read the shell of the deploy step out of the workflow.
   *
   * @return string
   *   The step's `run` script.
   */
  protected static function stepScript(): string {
    $script = self::child(self::deployStep(self::STEP), 'run');

    if (!is_string($script)) {
      self::fail(sprintf('The "%s" step of %s runs no script.', self::STEP, basename(self::deployWorkflowPath())));
    }

    return $script;
  }

}
