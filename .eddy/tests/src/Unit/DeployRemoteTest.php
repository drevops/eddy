<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Traits\GitTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;

/**
 * Tests what the deploy script pushes to a remote repository.
 *
 * The script runs in a clone with HEAD detached at the deployed commit, as the
 * workflow's checkout leaves it. It pushes to a local bare repository, and the
 * assertions read the refs that repository received.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class DeployRemoteTest extends UnitTestCase {

  use GitTrait;

  protected const string BRANCH = '1.x';

  protected const string RELEASE_TAG = '1.0.0';

  protected const string LATER_TAG = '1.1.0';

  public function testBranchDeployment(): void {
    $clone = $this->createClone();
    $remote = $this->createRemote();
    $this->git($clone, ['checkout', '--quiet', '--detach', 'main']);

    $output = $this->runDeploy($clone, $remote, ['DEPLOY_BRANCH' => self::BRANCH]);

    $this->assertSame($this->commit($clone, 'tip'), $this->remoteCommit($remote, 'refs/heads/' . self::BRANCH));
    $this->assertSame($this->commit($clone, 'released'), $this->remoteCommit($remote, 'refs/tags/' . self::RELEASE_TAG));
    $this->assertSame($this->commit($clone, 'tip'), $this->remoteCommit($remote, 'refs/tags/' . self::LATER_TAG));
    $this->assertStringContainsString('Tags pushed to', $output);
  }

  public function testTagDeployment(): void {
    $clone = $this->createClone();
    $remote = $this->createRemote();

    // The remote branch is ahead of the release, as after a later deployment.
    $this->git($clone, ['push', '--quiet', $remote, 'main:refs/heads/' . self::BRANCH]);

    $this->git($clone, ['branch', self::RELEASE_TAG, 'main']);

    $this->git($clone, ['checkout', '--quiet', '--detach', 'refs/tags/' . self::RELEASE_TAG . '^{commit}']);

    $output = $this->runDeploy($clone, $remote, ['DEPLOY_TAG' => self::RELEASE_TAG]);

    $this->assertSame($this->commit($clone, 'released'), $this->remoteCommit($remote, 'refs/tags/' . self::RELEASE_TAG));
    $this->assertSame($this->commit($clone, 'tip'), $this->remoteCommit($remote, 'refs/heads/' . self::BRANCH), 'A tag deployment must leave the remote branches untouched.');
    $this->assertNull($this->remoteCommit($remote, 'refs/heads/' . self::RELEASE_TAG), 'A tag deployment must not push a branch that shares the tag name.');
    $this->assertNull($this->remoteCommit($remote, 'refs/tags/' . self::LATER_TAG), 'A tag deployment must push only its own tag.');
    $this->assertStringContainsString('Tag pushed to', $output);
  }

  public function testRefusedTagIsReported(): void {
    $clone = $this->createClone();
    $remote = $this->createRemote();
    $this->git($clone, ['checkout', '--quiet', '--detach', 'main']);

    // The update hook refuses the later tag and accepts every other ref.
    $hook = $remote . '/hooks/update';
    file_put_contents($hook, sprintf("#!/usr/bin/env bash\n[ \"\$1\" != %s ]\n", escapeshellarg('refs/tags/' . self::LATER_TAG)));
    chmod($hook, 0755);

    $output = $this->runDeploy($clone, $remote, ['DEPLOY_BRANCH' => self::BRANCH]);

    $this->assertSame($this->commit($clone, 'tip'), $this->remoteCommit($remote, 'refs/heads/' . self::BRANCH));
    $this->assertSame($this->commit($clone, 'released'), $this->remoteCommit($remote, 'refs/tags/' . self::RELEASE_TAG));
    $this->assertNull($this->remoteCommit($remote, 'refs/tags/' . self::LATER_TAG));
    $this->assertStringContainsString('[remote rejected] ' . self::LATER_TAG, $output);
    $this->assertStringContainsString('Some tags were not pushed to', $output);
    $this->assertStringContainsString('DEPLOY COMPLETE', $output);
  }

  /**
   * Run the deploy script in a clone.
   *
   * @param string $clone
   *   Directory of the clone to deploy from.
   * @param string $remote
   *   Directory of the repository to deploy to.
   * @param array<string, string> $environment
   *   Variables that select what is deployed.
   *
   * @return string
   *   The standard output of the script.
   */
  protected function runDeploy(string $clone, string $remote, array $environment): string {
    $environment += [
      'DEPLOY_USER_NAME' => 'Deploy Bot',
      'DEPLOY_USER_EMAIL' => 'deploy@example.com',
      'DEPLOY_REMOTE' => $remote,
      'DEPLOY_PROCEED' => '1',
      'DEPLOY_BRANCH' => '',
      'DEPLOY_TAG' => '',
      // A fingerprint makes the script rewrite ~/.ssh/config, so it is cleared.
      'DEPLOY_SSH_KEY_FINGERPRINT' => '',
      'DEBUG' => '',
      'TERM' => 'dumb',
      // The script writes the git identity to the global configuration.
      'GIT_CONFIG_GLOBAL' => self::$tmp . '/gitconfig',
    ];

    $process = new Process([PHP_BINARY, dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy'], $clone, $environment + self::gitEnvironment());
    $process->run();

    if (!$process->isSuccessful()) {
      self::fail(sprintf("The deploy script failed:\n%s%s", $process->getOutput(), $process->getErrorOutput()));
    }

    return $process->getOutput();
  }

  /**
   * Create a clone with 2 commits on 'main', each with a tag.
   *
   * The first commit holds an annotated release tag and the second holds a
   * lightweight tag.
   *
   * @return string
   *   Directory of the clone.
   */
  protected function createClone(): string {
    $clone = self::$tmp . '/clone';
    mkdir($clone, 0755, TRUE);

    $this->git($clone, ['init', '--quiet', '--initial-branch=main']);
    $this->git($clone, ['config', 'user.name', 'Test User']);
    $this->git($clone, ['config', 'user.email', 'test@example.com']);

    file_put_contents($clone . '/file.txt', 'first');
    $this->git($clone, ['add', '--all']);
    $this->git($clone, ['commit', '--quiet', '--message', 'First commit']);
    $this->git($clone, ['tag', '--annotate', self::RELEASE_TAG, '--message', 'Release']);

    file_put_contents($clone . '/file.txt', 'second');
    $this->git($clone, ['commit', '--quiet', '--all', '--message', 'Second commit']);
    $this->git($clone, ['tag', self::LATER_TAG]);

    return $clone;
  }

  protected function createRemote(): string {
    $remote = self::$tmp . '/remote.git';
    mkdir($remote, 0755, TRUE);

    $this->git($remote, ['init', '--quiet', '--bare']);

    return $remote;
  }

  /**
   * Resolve a commit of the clone built by ::createClone().
   *
   * @param string $clone
   *   Directory of the clone.
   * @param string $name
   *   Either 'released' or 'tip'.
   *
   * @return string
   *   The commit SHA.
   */
  protected function commit(string $clone, string $name): string {
    return match ($name) {
      'released' => $this->git($clone, ['rev-parse', 'refs/tags/' . self::RELEASE_TAG . '^{commit}']),
      'tip' => $this->git($clone, ['rev-parse', 'refs/heads/main']),
      default => self::fail(sprintf('Unknown commit "%s".', $name)),
    };
  }

  /**
   * Resolve a ref of the remote to the commit it points at.
   *
   * @param string $remote
   *   Directory of the remote repository.
   * @param string $ref
   *   Full name of the ref.
   *
   * @return string|null
   *   The commit SHA, or NULL when the remote does not hold the ref.
   */
  protected function remoteCommit(string $remote, string $ref): ?string {
    $process = new Process(['git', 'rev-parse', '--verify', '--quiet', $ref . '^{commit}'], $remote, self::gitEnvironment());
    $process->run();

    return $process->isSuccessful() ? trim($process->getOutput()) : NULL;
  }

}
