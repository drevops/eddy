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
 * A branch push deploys only while its commit is the branch tip on 'origin'.
 * Runs finish out of order and can be re-run, and deploying an older commit
 * would rewind the remote branch.
 *
 * The branch can also move after the checkout, so the step fetches it again
 * before the comparison. The checkout keeps no credentials, so the fetch sends
 * the token itself.
 *
 * The step's shell is read out of the workflow and executed in a clone of a
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
  public function testDeployment(string $deploy_branch, string $head_branch, string $head_commit, string $after_checkout, ?array $expected): void {
    $repository = $this->createRepository();
    $head_sha = $this->resolveCommit($repository, $head_commit);

    $this->changeOrigin($head_branch, $after_checkout);

    $this->runStep($repository, [
      'DEPLOY_BRANCH' => $deploy_branch,
      'HEAD_BRANCH' => $head_branch,
      'HEAD_SHA' => $head_sha,
    ]);

    $this->assertSame($expected, $this->readDeployment());
  }

  public static function dataProviderDeployment(): \Iterator {
    yield 'branch push' => [
      'deploy_branch' => '',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tip',
      'after_checkout' => 'none',
      'expected' => ['DEPLOY_BRANCH' => self::FEATURE_BRANCH, 'DEPLOY_TAG' => ''],
    ];

    yield 'lightweight tag push' => [
      'deploy_branch' => '',
      'head_branch' => self::LIGHTWEIGHT_TAG,
      'head_commit' => 'tagged',
      'after_checkout' => 'none',
      'expected' => ['DEPLOY_BRANCH' => '', 'DEPLOY_TAG' => self::LIGHTWEIGHT_TAG],
    ];

    yield 'annotated tag push' => [
      'deploy_branch' => '',
      'head_branch' => self::ANNOTATED_TAG,
      'head_commit' => 'tagged',
      'after_checkout' => 'none',
      'expected' => ['DEPLOY_BRANCH' => '', 'DEPLOY_TAG' => self::ANNOTATED_TAG],
    ];

    yield 'branch sharing a name with a tag on another commit' => [
      'deploy_branch' => '',
      'head_branch' => self::LIGHTWEIGHT_TAG,
      'head_commit' => 'tip',
      'after_checkout' => 'none',
      'expected' => ['DEPLOY_BRANCH' => self::LIGHTWEIGHT_TAG, 'DEPLOY_TAG' => ''],
    ];

    yield 'repository variable redirects a branch push' => [
      'deploy_branch' => 'custom',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tip',
      'after_checkout' => 'none',
      'expected' => ['DEPLOY_BRANCH' => 'custom', 'DEPLOY_TAG' => ''],
    ];

    yield 'repository variable leaves a tag push alone' => [
      'deploy_branch' => 'custom',
      'head_branch' => self::LIGHTWEIGHT_TAG,
      'head_commit' => 'tagged',
      'after_checkout' => 'none',
      'expected' => ['DEPLOY_BRANCH' => '', 'DEPLOY_TAG' => self::LIGHTWEIGHT_TAG],
    ];

    yield 'branch moved past the commit' => [
      'deploy_branch' => '',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tagged',
      'after_checkout' => 'none',
      'expected' => NULL,
    ];

    yield 'repository variable does not override a branch that moved' => [
      'deploy_branch' => 'custom',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tagged',
      'after_checkout' => 'none',
      'expected' => NULL,
    ];

    yield 'branch moved past the commit after the checkout' => [
      'deploy_branch' => '',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tip',
      'after_checkout' => 'advance',
      'expected' => NULL,
    ];

    yield 'branch deleted after the checkout' => [
      'deploy_branch' => '',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tip',
      'after_checkout' => 'delete',
      'expected' => NULL,
    ];

    yield 'branch reset to the commit after the checkout' => [
      'deploy_branch' => '',
      'head_branch' => self::FEATURE_BRANCH,
      'head_commit' => 'tagged',
      'after_checkout' => 'rewind',
      'expected' => ['DEPLOY_BRANCH' => self::FEATURE_BRANCH, 'DEPLOY_TAG' => ''],
    ];

    yield 'deleted branch' => [
      'deploy_branch' => '',
      'head_branch' => 'deleted',
      'head_commit' => 'tip',
      'after_checkout' => 'none',
      'expected' => NULL,
    ];

    yield 'no head branch' => [
      'deploy_branch' => '',
      'head_branch' => '',
      'head_commit' => 'tip',
      'after_checkout' => 'none',
      'expected' => NULL,
    ];

    yield 'head commit absent from the clone' => [
      'deploy_branch' => '',
      'head_branch' => self::LIGHTWEIGHT_TAG,
      'head_commit' => 'unknown',
      'after_checkout' => 'none',
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

  public function testFailedFetchFailsStep(): void {
    $repository = $this->createRepository();
    $this->git($repository, ['remote', 'set-url', 'origin', self::$tmp . '/missing']);

    $process = $this->startStep($repository, [
      'DEPLOY_BRANCH' => '',
      'HEAD_BRANCH' => self::FEATURE_BRANCH,
      'HEAD_SHA' => $this->resolveCommit($repository, 'tip'),
    ]);
    $process->wait();

    $this->assertFalse($process->isSuccessful(), 'A failed fetch must fail the step rather than compare the commit with the tip the checkout saw.');
    $this->assertNull($this->readDeployment());
  }

  public function testFetchSendsToken(): void {
    $repository = $this->createRepository();
    // Long enough for 'base64' to wrap the encoded credentials.
    $token = 'ghs_' . str_repeat('0123456789', 10);

    $server = stream_socket_server('tcp://127.0.0.1:0');

    if ($server === FALSE) {
      self::fail('Unable to listen on a local port.');
    }

    $address = stream_socket_get_name($server, FALSE);

    if ($address === FALSE) {
      self::fail('Unable to read the address of the local port.');
    }

    $this->git($repository, ['remote', 'set-url', 'origin', sprintf('http://%s/repository.git', $address)]);

    $process = $this->startStep($repository, [
      'DEPLOY_BRANCH' => '',
      'HEAD_BRANCH' => self::FEATURE_BRANCH,
      'HEAD_SHA' => $this->resolveCommit($repository, 'tip'),
      'GITHUB_TOKEN' => $token,
      // A proxy set on the host would otherwise receive the request.
      'NO_PROXY' => '*',
      'no_proxy' => '*',
    ]);

    $headers = self::readRequestHeaders($server);
    $process->wait();

    $this->assertSame('basic ' . base64_encode('x-access-token:' . $token), $headers['authorization'] ?? NULL, 'The fetch must authenticate with the token, otherwise it fails on a private repository.');
  }

  public function testStepReceivesToken(): void {
    $token = self::child(self::child(self::deployStep(self::STEP), 'env'), 'GITHUB_TOKEN');

    $this->assertSame('${{ secrets.GITHUB_TOKEN }}', $token, sprintf('The "%s" step must receive GITHUB_TOKEN, since its fetch needs credentials on a private repository.', self::STEP));
  }

  public function testStepTakesEveryValueFromEnvironment(): void {
    $this->assertStringNotContainsString('${{', self::stepScript(), sprintf('The "%s" step must read every value through `env:` so that its shell can be executed as it stands.', self::STEP));
  }

  /**
   * Start the deploy step in a repository.
   *
   * @param string $repository
   *   Directory of the repository to run in.
   * @param array<string, string> $environment
   *   Environment the workflow defines for the step.
   *
   * @return \Symfony\Component\Process\Process
   *   The started step.
   */
  protected function startStep(string $repository, array $environment): Process {
    $script = self::$tmp . '/step.sh';
    file_put_contents($script, self::stepScript());

    // GitHub Actions runs a `run` block as `bash -e {0}`.
    $process = new Process(['bash', '-e', $script], $repository, $environment + self::gitEnvironment());
    $process->start();

    return $process;
  }

  /**
   * Run the deploy step in a repository.
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
    $process = $this->startStep($repository, $environment);
    $process->wait();

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
   * The clone holds every branch of 'origin' as a remote-tracking ref, and
   * every tag. A branch and a tag share a name, with the tag on an earlier
   * commit.
   *
   * @return string
   *   Directory of the clone.
   */
  protected function createRepository(): string {
    $origin = self::originDir();
    mkdir($origin, 0755, TRUE);

    $this->git($origin, ['init', '--initial-branch=' . self::DEFAULT_BRANCH]);
    $this->git($origin, ['config', 'user.name', 'Test User']);
    $this->git($origin, ['config', 'user.email', 'test@example.com']);

    file_put_contents($origin . '/file.txt', 'first');
    $this->git($origin, ['add', '--all']);
    $this->git($origin, ['commit', '--message', 'First commit']);

    $this->git($origin, ['tag', self::LIGHTWEIGHT_TAG]);
    $this->git($origin, ['tag', '--annotate', self::ANNOTATED_TAG, '--message', 'Release']);

    file_put_contents($origin . '/file.txt', 'second');
    $this->git($origin, ['commit', '--all', '--message', 'Second commit']);

    foreach ([self::FEATURE_BRANCH, self::LIGHTWEIGHT_TAG] as $branch) {
      $this->git($origin, ['update-ref', 'refs/heads/' . $branch, 'HEAD']);
    }

    $repository = self::$tmp . '/repository';
    $this->git(self::$tmp, ['clone', '--quiet', $origin, $repository]);

    mkdir($repository . '/vendor/bin', 0755, TRUE);

    // The step ends by invoking the deploy command, so this stub records the
    // variables it receives.
    file_put_contents($repository . '/vendor/bin/eddy-deploy', sprintf("#!/usr/bin/env bash\nprintf 'DEPLOY_BRANCH=%%s\\nDEPLOY_TAG=%%s\\n' \"\${DEPLOY_BRANCH:-}\" \"\${DEPLOY_TAG:-}\" > %s\n", escapeshellarg(self::deploymentFile())));
    chmod($repository . '/vendor/bin/eddy-deploy', 0755);

    return $repository;
  }

  /**
   * Change a branch of 'origin' after the clone was made.
   *
   * @param string $branch
   *   Name of the branch.
   * @param string $change
   *   'none', 'advance' to add a commit, 'delete', or 'rewind' to reset the
   *   branch to its parent commit.
   */
  protected function changeOrigin(string $branch, string $change): void {
    $origin = self::originDir();
    $ref = 'refs/heads/' . $branch;

    $arguments = match ($change) {
      'none' => NULL,
      'advance' => ['update-ref', $ref, $this->git($origin, ['commit-tree', $ref . '^{tree}', '-p', $ref, '-m', 'Third commit'])],
      'delete' => ['update-ref', '-d', $ref],
      'rewind' => ['update-ref', $ref, $ref . '~1'],
      default => self::fail(sprintf('Unknown change "%s".', $change)),
    };

    if ($arguments !== NULL) {
      $this->git($origin, $arguments);
    }
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
   * Answer 1 request on a socket with a 404 and read the request headers.
   *
   * @param resource $server
   *   The listening socket.
   *
   * @return array<string, string>
   *   The request headers, keyed by lowercase name.
   */
  protected static function readRequestHeaders($server): array {
    $connection = stream_socket_accept($server, 10);

    if ($connection === FALSE) {
      self::fail('The deploy step did not fetch from the remote.');
    }

    stream_set_timeout($connection, 10);

    // The request line holds no header.
    fgets($connection);

    $headers = [];

    while (($line = fgets($connection)) !== FALSE && trim($line) !== '') {
      [$name, $value] = explode(':', $line, 2) + [1 => ''];
      $headers[strtolower(trim($name))] = trim($value);
    }

    fwrite($connection, "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
    fclose($connection);
    fclose($server);

    return $headers;
  }

  /**
   * Path of the repository the clone takes as 'origin'.
   *
   * @return string
   *   The absolute path.
   */
  protected static function originDir(): string {
    return self::$tmp . '/origin';
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
