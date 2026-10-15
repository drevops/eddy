<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-deploy' command.
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class DeployTest extends UnitTestCase {

  #[DataProvider('dataProviderDeploySkipsWhenProceedIsNotOne')]
  public function testDeploySkipsWhenProceedIsNotOne(?string $deploy_proceed): void {
    self::envSet('DEPLOY_USER_NAME', 'Test User');
    self::envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    self::envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');

    if ($deploy_proceed !== NULL) {
      self::envSet('DEPLOY_PROCEED', $deploy_proceed);
    }

    $this->mockQuit(0);

    $output = $this->runCommand('eddy-deploy', 0);

    $this->assertStringContainsString('DEPLOY', $output);
    $this->assertStringContainsString('Skip deployment because DEPLOY_PROCEED is not set to 1', $output);
  }

  public static function dataProviderDeploySkipsWhenProceedIsNotOne(): \Iterator {
    yield 'unset' => [NULL];
    yield 'zero' => ['0'];
  }

  #[DataProvider('dataProviderDeployProceed')]
  public function testDeployProceed(string $deploy_branch, string $git_user_name, string $git_user_email): void {
    $deploy_remote = 'git@git.drupal.org:project/test.git';

    self::envSet('DEPLOY_USER_NAME', 'Deploy Bot');
    self::envSet('DEPLOY_USER_EMAIL', 'deploy@example.com');
    self::envSet('DEPLOY_REMOTE', $deploy_remote);
    self::envSet('DEPLOY_PROCEED', '1');

    if ($deploy_branch !== '') {
      self::envSet('DEPLOY_BRANCH', $deploy_branch);
    }

    $shell_exec_calls = [];
    $this->mockFunction('shell_exec', function (string $command) use (&$shell_exec_calls, $git_user_name, $git_user_email): string {
      $shell_exec_calls[] = $command;
      if (str_contains($command, 'user.name')) {
        return $git_user_name;
      }
      if (str_contains($command, 'user.email')) {
        return $git_user_email;
      }
      if (str_contains($command, 'symbolic-ref')) {
        return 'main';
      }
      return '';
    });

    $passthru_responses = [];

    if (trim($git_user_name) === '') {
      $passthru_responses[] = ['cmd' => sprintf('git config --global user.name %s', escapeshellarg('Deploy Bot'))];
    }

    if (trim($git_user_email) === '') {
      $passthru_responses[] = ['cmd' => sprintf('git config --global user.email %s', escapeshellarg('deploy@example.com'))];
    }

    $passthru_responses[] = ['cmd' => 'git config --global push.default matching'];

    $passthru_responses[] = ['cmd' => sprintf('git remote add deployremote %s', escapeshellarg($deploy_remote))];

    $effective_branch = $deploy_branch !== '' ? $deploy_branch : 'main';
    $passthru_responses[] = ['cmd' => sprintf('git push --force deployremote %s', escapeshellarg('HEAD:refs/heads/' . $effective_branch))];

    $passthru_responses[] = ['cmd' => 'git push --force --tags deployremote'];

    $this->mockPassthruMultiple($passthru_responses);

    $output = $this->runCommand('eddy-deploy');

    $this->assertStringContainsString('DEPLOY', $output);
    $this->assertStringContainsString('Pushing code to branch ' . $effective_branch, $output);
    $this->assertStringContainsString('Code pushed to ' . $deploy_remote . ':' . $effective_branch, $output);
    $this->assertStringContainsString('Tags pushed to ' . $deploy_remote, $output);
    $this->assertStringNotContainsString('Some tags were not pushed', $output);
    $this->assertStringContainsString('DEPLOY COMPLETE', $output);
    $this->assertStringContainsString('Remote URL    : ' . $deploy_remote, $output);
    $this->assertStringContainsString('Remote branch : ' . $effective_branch, $output);
  }

  public static function dataProviderDeployProceed(): \Iterator {
    yield 'custom branch, no existing git config' => [
      'deploy_branch' => '1.x',
      'git_user_name' => '',
      'git_user_email' => '',
    ];
    yield 'auto-detect branch, existing git config' => [
      'deploy_branch' => '',
      'git_user_name' => 'Existing User',
      'git_user_email' => 'existing@example.com',
    ];
    yield 'custom branch, existing user name only' => [
      'deploy_branch' => '2.x',
      'git_user_name' => 'Existing User',
      'git_user_email' => '',
    ];
    yield 'auto-detect branch, existing email only' => [
      'deploy_branch' => '',
      'git_user_name' => '',
      'git_user_email' => 'existing@example.com',
    ];
  }

  public function testDeployDetachedHeadWithoutBranch(): void {
    self::envSet('DEPLOY_USER_NAME', 'Deploy Bot');
    self::envSet('DEPLOY_USER_EMAIL', 'deploy@example.com');
    self::envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    self::envSet('DEPLOY_PROCEED', '1');

    $this->mockShellExecOutputs(['git symbolic-ref --quiet --short HEAD' => NULL]);

    $this->mockPassthruNever();
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-deploy', 1);

    $this->assertStringContainsString('Unable to determine the branch to deploy because HEAD is detached', $output);
  }

  public function testDeployRefusedTags(): void {
    $deploy_remote = 'git@git.drupal.org:project/test.git';

    self::envSet('DEPLOY_USER_NAME', 'Deploy Bot');
    self::envSet('DEPLOY_USER_EMAIL', 'deploy@example.com');
    self::envSet('DEPLOY_REMOTE', $deploy_remote);
    self::envSet('DEPLOY_PROCEED', '1');
    self::envSet('DEPLOY_BRANCH', '1.x');

    $this->mockFunction('shell_exec', fn(): string => 'Existing');

    $this->mockPassthruMultiple([
      ['cmd' => 'git config --global push.default matching'],
      ['cmd' => sprintf('git remote add deployremote %s', escapeshellarg($deploy_remote))],
      ['cmd' => sprintf('git push --force deployremote %s', escapeshellarg('HEAD:refs/heads/1.x'))],
      [
        'cmd' => 'git push --force --tags deployremote',
        'output' => ' ! [remote rejected] 1.0.0 -> 1.0.0 (pre-receive hook declined)' . PHP_EOL,
        'result_code' => 1,
      ],
    ]);

    $output = $this->runCommand('eddy-deploy');

    $this->assertStringContainsString('Code pushed to ' . $deploy_remote . ':1.x', $output);
    $this->assertStringContainsString('[remote rejected] 1.0.0 -> 1.0.0', $output);
    $this->assertStringContainsString('Some tags were not pushed to ' . $deploy_remote, $output);
    $this->assertStringNotContainsString('Tags pushed to', $output);
    $this->assertStringContainsString('DEPLOY COMPLETE', $output);
    $this->assertStringContainsString('Remote branch : 1.x', $output);
  }

  #[DataProvider('dataProviderDeployTag')]
  public function testDeployTag(string $deploy_branch): void {
    $deploy_remote = 'git@git.drupal.org:project/test.git';

    self::envSet('DEPLOY_USER_NAME', 'Deploy Bot');
    self::envSet('DEPLOY_USER_EMAIL', 'deploy@example.com');
    self::envSet('DEPLOY_REMOTE', $deploy_remote);
    self::envSet('DEPLOY_PROCEED', '1');
    self::envSet('DEPLOY_TAG', '1.2.0');

    if ($deploy_branch !== '') {
      self::envSet('DEPLOY_BRANCH', $deploy_branch);
    }

    $this->mockFunction('shell_exec', fn(string $command): string => str_contains($command, 'symbolic-ref') ? throw new \RuntimeException('A tag deployment must not resolve a branch.') : 'Existing');

    // A branch push or a push of every tag would be an unexpected call.
    $this->mockPassthruMultiple([
      ['cmd' => 'git config --global push.default matching'],
      ['cmd' => sprintf('git remote add deployremote %s', escapeshellarg($deploy_remote))],
      ['cmd' => sprintf('git push --force deployremote %s', escapeshellarg('refs/tags/1.2.0'))],
    ]);

    $output = $this->runCommand('eddy-deploy');

    $this->assertStringContainsString('Pushing tag 1.2.0', $output);
    $this->assertStringContainsString('Tag pushed to ' . $deploy_remote . ':1.2.0', $output);
    $this->assertStringContainsString('DEPLOY COMPLETE', $output);
    $this->assertStringContainsString('Remote tag    : 1.2.0', $output);
    $this->assertStringNotContainsString('Remote branch', $output);
  }

  public static function dataProviderDeployTag(): \Iterator {
    yield 'tag' => [
      'deploy_branch' => '',
    ];
    yield 'tag with a branch set' => [
      'deploy_branch' => '1.x',
    ];
  }

  public function testDeployTagPushFailure(): void {
    $deploy_remote = 'git@git.drupal.org:project/test.git';

    self::envSet('DEPLOY_USER_NAME', 'Deploy Bot');
    self::envSet('DEPLOY_USER_EMAIL', 'deploy@example.com');
    self::envSet('DEPLOY_REMOTE', $deploy_remote);
    self::envSet('DEPLOY_PROCEED', '1');
    self::envSet('DEPLOY_TAG', '1.2.0');

    $this->mockFunction('shell_exec', fn(): string => 'Existing');

    $this->mockPassthruMultiple([
      ['cmd' => 'git config --global push.default matching'],
      ['cmd' => sprintf('git remote add deployremote %s', escapeshellarg($deploy_remote))],
      [
        'cmd' => sprintf('git push --force deployremote %s', escapeshellarg('refs/tags/1.2.0')),
        'output' => ' ! [remote rejected] 1.2.0 -> 1.2.0 (pre-receive hook declined)' . PHP_EOL,
        'result_code' => 1,
      ],
    ]);

    $this->mockQuit(1);

    $output = $this->runCommand('eddy-deploy', 1);

    $this->assertStringContainsString('[remote rejected] 1.2.0 -> 1.2.0', $output);
    $this->assertStringNotContainsString('Tag pushed', $output);
  }

  public function testDeployMissingRequiredVars(): void {
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-deploy', 1);

    $this->assertStringContainsString('Missing required value for DEPLOY_USER_NAME', $output);
  }

  /**
   * @param array<string>|null $glob
   *   The key files that 'glob()' finds, or NULL to leave it unmocked.
   */
  #[DataProvider('dataProviderDeploySshKeyFingerprint')]
  public function testDeploySshKeyFingerprint(string $fingerprint, ?array $glob, string $key_file): void {
    self::envSet('DEPLOY_USER_NAME', 'Test User');
    self::envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    self::envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    self::envSet('DEPLOY_SSH_KEY_FINGERPRINT', $fingerprint);
    self::envSet('HOME', '/home/testuser');

    $this->mockFunction('is_dir', fn(string $path): bool => str_contains($path, '.ssh'));

    $this->mockFunction('file_put_contents', fn(): int => 100);

    if ($glob !== NULL) {
      $this->mockFunction('glob', fn(): array => $glob);
      $this->mockFunction('exec', function (string $command, ?array &$output = NULL): int {
        $output ??= [];

        if (str_contains($command, '-E sha256')) {
          $output[] = '2048 SHA256:abcdef123456 testuser@host (RSA)';
        }
        elseif (str_contains($command, '-E md5')) {
          $output[] = '2048 MD5:aa:bb:cc:dd testuser@host (RSA)';
        }

        return 0;
      });
    }

    $this->mockFunction('file_exists', fn(string $file): bool => $file === $key_file);

    // The running agent is reused, so 'ssh-agent' never runs.
    self::envSet('SSH_AGENT_PID', '12345');
    $this->mockShellExecOutputs([]);

    $this->mockPassthruMultiple([
      ['cmd' => 'ssh-add -D'],
      ['cmd' => sprintf('ssh-add %s', escapeshellarg($key_file))],
      ['cmd' => 'ssh-add -l'],
    ]);

    // DEPLOY_PROCEED is not 1, so it skips.
    $this->mockQuit(0);

    $output = $this->runCommand('eddy-deploy', 0);

    $this->assertStringContainsString('Setting up SSH.', $output);
    $this->assertStringContainsString('SSH key ' . $key_file . ' added.', $output);
    $this->assertStringContainsString('Skip deployment', $output);

    $banner = strpos($output, '🚚 DEPLOY');
    $ssh = strpos($output, 'Setting up SSH.');
    $skip = strpos($output, 'Skip deployment');
    $this->assertIsInt($banner);
    $this->assertIsInt($ssh);
    $this->assertIsInt($skip);
    $this->assertLessThan($ssh, $banner, 'The banner must open the output, before the SSH setup.');
    $this->assertLessThan($skip, $ssh, 'SSH must be set up before the proceed check.');
  }

  public static function dataProviderDeploySshKeyFingerprint(): \Iterator {
    // The fingerprint is cleaned to 'aabbccddeeff'.
    yield 'MD5' => ['aa:bb:cc:dd:ee:ff', NULL, '/home/testuser/.ssh/id_rsa_aabbccddeeff'];
    // The MD5 fingerprint is extracted and cleaned to 'aabbccdd'.
    yield 'SHA256 converted to MD5' => ['SHA256:abcdef123456', ['/home/testuser/.ssh/id_rsa_test'], '/home/testuser/.ssh/id_rsa_aabbccdd'];
  }

  /**
   * @param array<string>|false|null $glob
   *   The result of 'glob()', or NULL to leave it unmocked.
   * @param array<string>|null $sha256_output
   *   The SHA256 lines that 'exec()' returns, or NULL to leave it unmocked.
   */
  #[DataProvider('dataProviderDeployFailsWhenSshKeyIsMissing')]
  public function testDeployFailsWhenSshKeyIsMissing(string $fingerprint, array|false|null $glob, ?array $sha256_output): void {
    self::envSet('DEPLOY_USER_NAME', 'Test User');
    self::envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    self::envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    self::envSet('DEPLOY_SSH_KEY_FINGERPRINT', $fingerprint);
    self::envSet('HOME', '/home/testuser');

    $this->mockFunction('is_dir', fn(): true => TRUE);
    $this->mockFunction('file_put_contents', fn(): int => 100);

    if ($glob !== NULL) {
      $this->mockFunction('glob', fn(): array|false => $glob);
    }

    if ($sha256_output !== NULL) {
      $this->mockFunction('exec', function (string $command, ?array &$output = NULL) use ($sha256_output): int {
        $output ??= [];

        if (str_contains($command, '-E sha256')) {
          $output = array_merge($output, $sha256_output);
        }

        return 0;
      });
    }

    $this->mockFunction('file_exists', fn(): false => FALSE);

    $this->mockQuit(1);

    $output = $this->runCommand('eddy-deploy', 1);

    $this->assertStringContainsString('Unable to find SSH key file', $output);
  }

  public static function dataProviderDeployFailsWhenSshKeyIsMissing(): \Iterator {
    yield 'MD5 key missing' => ['aa:bb:cc:dd', NULL, NULL];
    // The unconverted fingerprint cleans to 'SHA256nomatch'.
    yield 'SHA256 matches no key' => ['SHA256:nomatch', ['/home/testuser/.ssh/id_rsa_test'], ['2048 SHA256:differenthash testuser@host (RSA)']];
    // The SHA256 fingerprint is not converted, so no key file matches.
    yield 'SHA256 with no key files' => ['SHA256:abcdef', FALSE, NULL];
    yield 'SHA256 with empty ssh-keygen output' => ['SHA256:abcdef', ['/home/testuser/.ssh/id_rsa_test'], []];
  }

  public function testDeploySshCreatesSshDir(): void {
    self::envSet('DEPLOY_USER_NAME', 'Test User');
    self::envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    self::envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    self::envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'aa:bb:cc:dd');
    self::envSet('HOME', '/home/testuser');

    $this->mockFunction('is_dir', fn(): false => FALSE);

    $mkdir_calls = [];
    $this->mockFunction('mkdir', function (string $dir) use (&$mkdir_calls): true {
      $mkdir_calls[] = $dir;
      return TRUE;
    });

    $this->mockFunction('file_put_contents', fn(): int => 100);

    $key_file = '/home/testuser/.ssh/id_rsa_aabbccdd';
    $this->mockFunction('file_exists', fn(string $file): bool => $file === $key_file);

    self::envSet('SSH_AGENT_PID', '12345');

    $this->mockPassthruMultiple([
      ['cmd' => 'ssh-add -D'],
      ['cmd' => sprintf('ssh-add %s', escapeshellarg($key_file))],
      ['cmd' => 'ssh-add -l'],
    ]);

    $this->mockQuit(0);

    $this->runCommand('eddy-deploy', 0);

    $this->assertContains('/home/testuser/.ssh', $mkdir_calls);
  }

  #[DataProvider('dataProviderDeploySshAgentStarted')]
  public function testDeploySshAgentStarted(?string $agent_pid): void {
    self::envSet('DEPLOY_USER_NAME', 'Test User');
    self::envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    self::envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    self::envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'aa:bb:cc:dd');
    self::envSet('HOME', '/home/testuser');
    // The socket of an agent the script did not start, such as a forwarded
    // one, which 'ssh-add -D' would clear.
    self::envSet('SSH_AUTH_SOCK', '/home/testuser/.ssh/agent/s.other');

    if ($agent_pid === NULL) {
      self::envUnset('SSH_AGENT_PID');
    }
    else {
      self::envSet('SSH_AGENT_PID', $agent_pid);
    }

    $this->mockFunction('is_dir', fn(): true => TRUE);
    $this->mockFunction('file_put_contents', fn(): int => 100);

    $key_file = '/home/testuser/.ssh/id_rsa_aabbccdd';
    $this->mockFunction('file_exists', fn(string $file): bool => $file === $key_file);

    $this->mockShellExecOutputs(['ssh-agent -s' => "SSH_AUTH_SOCK=/home/testuser/.ssh/agent/s.started; export SSH_AUTH_SOCK;\nSSH_AGENT_PID=4242; export SSH_AGENT_PID;\necho Agent pid 4242;\n"]);

    $this->mockPassthruMultiple([
      ['cmd' => 'ssh-add -D'],
      ['cmd' => sprintf('ssh-add %s', escapeshellarg($key_file))],
      ['cmd' => 'ssh-add -l'],
    ]);

    $this->mockQuit(0);

    $output = $this->runCommand('eddy-deploy', 0);

    $this->assertStringContainsString('Started an SSH agent with PID 4242.', $output);
    $this->assertStringContainsString('SSH key ' . $key_file . ' added.', $output);

    $this->assertSame('/home/testuser/.ssh/agent/s.started', self::envGet('SSH_AUTH_SOCK'));
    $this->assertSame('4242', self::envGet('SSH_AGENT_PID'));
  }

  public static function dataProviderDeploySshAgentStarted(): \Iterator {
    yield 'SSH_AGENT_PID unset' => [NULL];
    yield 'SSH_AGENT_PID empty' => [''];
  }

  #[DataProvider('dataProviderDeploySshAgentStartFailure')]
  public function testDeploySshAgentStartFailure(string $agent_output): void {
    self::envSet('DEPLOY_USER_NAME', 'Test User');
    self::envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    self::envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    self::envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'aa:bb:cc:dd');
    self::envSet('HOME', '/home/testuser');
    self::envUnset('SSH_AGENT_PID');

    $this->mockFunction('is_dir', fn(): true => TRUE);
    $this->mockFunction('file_put_contents', fn(): int => 100);
    $this->mockFunction('file_exists', fn(): true => TRUE);

    $this->mockShellExecOutputs(['ssh-agent -s' => $agent_output]);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-deploy', 1);

    $this->assertStringContainsString('Unable to start the SSH agent.', $output);
  }

  public static function dataProviderDeploySshAgentStartFailure(): \Iterator {
    yield 'no output' => [''];
    yield 'no socket' => ["SSH_AGENT_PID=4242; export SSH_AGENT_PID;\necho Agent pid 4242;\n"];
    yield 'no process ID' => ["SSH_AUTH_SOCK=/home/testuser/.ssh/agent/s.started; export SSH_AUTH_SOCK;\n"];
  }

}
