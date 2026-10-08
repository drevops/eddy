<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use DrevOps\Eddy\Tests\Exceptions\QuitSuccessException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'eddy-deploy' command.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class DeployTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  public function testDeploySkipWhenProceedNotSet(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');

    $this->mockQuit(0);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitSuccessException to be thrown');
    }
    catch (QuitSuccessException $e) {
      $this->assertSame(0, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('DEPLOY', $output);
      $this->assertStringContainsString('Skip deployment because DEPLOY_PROCEED is not set to 1', $output);
    }
  }

  public function testDeploySkipWhenProceedExplicitlyZero(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_PROCEED', '0');

    $this->mockQuit(0);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitSuccessException to be thrown');
    }
    catch (QuitSuccessException $e) {
      $this->assertSame(0, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Skip deployment', $output);
    }
  }

  #[DataProvider('dataProviderDeployProceed')]
  public function testDeployProceed(string $deploy_branch, string $git_user_name, string $git_user_email): void {
    $deploy_remote = 'git@git.drupal.org:project/test.git';

    $this->envSet('DEPLOY_USER_NAME', 'Deploy Bot');
    $this->envSet('DEPLOY_USER_EMAIL', 'deploy@example.com');
    $this->envSet('DEPLOY_REMOTE', $deploy_remote);
    $this->envSet('DEPLOY_PROCEED', '1');

    if ($deploy_branch !== '') {
      $this->envSet('DEPLOY_BRANCH', $deploy_branch);
    }

    $shell_exec_calls = [];
    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd) use (&$shell_exec_calls, $git_user_name, $git_user_email): string {
      $shell_exec_calls[] = $cmd;
      if (str_contains($cmd, 'user.name')) {
        return $git_user_name;
      }
      if (str_contains($cmd, 'user.email')) {
        return $git_user_email;
      }
      if (str_contains($cmd, 'symbolic-ref')) {
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

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
    $output = ob_get_clean();

    $this->assertIsString($output);
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
    $this->envSet('DEPLOY_USER_NAME', 'Deploy Bot');
    $this->envSet('DEPLOY_USER_EMAIL', 'deploy@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_PROCEED', '1');

    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', fn(string $cmd): ?string => match ($cmd) {
      'git symbolic-ref --quiet --short HEAD' => NULL,
      default => throw new \RuntimeException(sprintf('Unexpected command "%s".', $cmd)),
    });

    $this->mockPassthruNever();
    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Unable to determine the branch to deploy because HEAD is detached', $output);
    }
  }

  public function testDeployRefusedTags(): void {
    $deploy_remote = 'git@git.drupal.org:project/test.git';

    $this->envSet('DEPLOY_USER_NAME', 'Deploy Bot');
    $this->envSet('DEPLOY_USER_EMAIL', 'deploy@example.com');
    $this->envSet('DEPLOY_REMOTE', $deploy_remote);
    $this->envSet('DEPLOY_PROCEED', '1');
    $this->envSet('DEPLOY_BRANCH', '1.x');

    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', fn(): string => 'Existing');

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

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
    $output = ob_get_clean();

    $this->assertIsString($output);
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

    $this->envSet('DEPLOY_USER_NAME', 'Deploy Bot');
    $this->envSet('DEPLOY_USER_EMAIL', 'deploy@example.com');
    $this->envSet('DEPLOY_REMOTE', $deploy_remote);
    $this->envSet('DEPLOY_PROCEED', '1');
    $this->envSet('DEPLOY_TAG', '1.2.0');

    if ($deploy_branch !== '') {
      $this->envSet('DEPLOY_BRANCH', $deploy_branch);
    }

    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', fn(string $cmd): string => str_contains($cmd, 'symbolic-ref') ? throw new \RuntimeException('A tag deployment must not resolve a branch.') : 'Existing');

    // A branch push or a push of every tag would be an unexpected call.
    $this->mockPassthruMultiple([
      ['cmd' => 'git config --global push.default matching'],
      ['cmd' => sprintf('git remote add deployremote %s', escapeshellarg($deploy_remote))],
      ['cmd' => sprintf('git push --force deployremote %s', escapeshellarg('refs/tags/1.2.0'))],
    ]);

    ob_start();
    require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
    $output = ob_get_clean();

    $this->assertIsString($output);
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

    $this->envSet('DEPLOY_USER_NAME', 'Deploy Bot');
    $this->envSet('DEPLOY_USER_EMAIL', 'deploy@example.com');
    $this->envSet('DEPLOY_REMOTE', $deploy_remote);
    $this->envSet('DEPLOY_PROCEED', '1');
    $this->envSet('DEPLOY_TAG', '1.2.0');

    $this->registerMock('shell_exec', 'DrevOps\\Eddy\\DevTools', fn(): string => 'Existing');

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

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('[remote rejected] 1.2.0 -> 1.2.0', $output);
      $this->assertStringNotContainsString('Tag pushed', $output);
    }
  }

  public function testDeployMissingRequiredVars(): void {
    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Missing required value for DEPLOY_USER_NAME', $output);
    }
  }

  public function testDeploySshKeyMd5Fingerprint(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'aa:bb:cc:dd:ee:ff');
    $this->envSet('HOME', '/home/testuser');

    $this->registerMock('is_dir', 'DrevOps\\Eddy\\DevTools', fn(string $path): bool => str_contains($path, '.ssh'));

    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', fn(): int => 100);

    // The fingerprint is cleaned to 'aabbccddeeff'.
    $key_file = '/home/testuser/.ssh/id_rsa_aabbccddeeff';

    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $file): bool => $file === $key_file);

    $this->envSet('SSH_AGENT_PID', '12345');

    $this->mockPassthruMultiple([
      ['cmd' => 'ssh-add -D'],
      ['cmd' => sprintf('ssh-add %s', escapeshellarg($key_file))],
      ['cmd' => 'ssh-add -l'],
    ]);

    // DEPLOY_PROCEED is not 1, so it skips.
    $this->mockQuit(0);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitSuccessException to be thrown');
    }
    catch (QuitSuccessException) {
      // Expected.
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
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
  }

  public function testDeploySshKeySha256Fingerprint(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'SHA256:abcdef123456');
    $this->envSet('HOME', '/home/testuser');

    $this->registerMock('is_dir', 'DrevOps\\Eddy\\DevTools', fn(string $path): bool => str_contains($path, '.ssh'));

    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', fn(): int => 100);

    $this->registerMock('glob', 'DrevOps\\Eddy\\DevTools', fn(): array => ['/home/testuser/.ssh/id_rsa_test']);

    $exec_calls = 0;
    $this->registerMock('exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd, ?array &$output = NULL) use (&$exec_calls): int {
      $exec_calls++;
      $output ??= [];
      if (str_contains($cmd, '-E sha256')) {
        $output[] = '2048 SHA256:abcdef123456 testuser@host (RSA)';
      }
      elseif (str_contains($cmd, '-E md5')) {
        $output[] = '2048 MD5:aa:bb:cc:dd testuser@host (RSA)';
      }
      return 0;
    });

    // The MD5 fingerprint is extracted and cleaned to 'aabbccdd'.
    $key_file = '/home/testuser/.ssh/id_rsa_aabbccdd';

    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $file): bool => $file === $key_file);

    $this->envSet('SSH_AGENT_PID', '12345');

    $this->mockPassthruMultiple([
      ['cmd' => 'ssh-add -D'],
      ['cmd' => sprintf('ssh-add %s', escapeshellarg($key_file))],
      ['cmd' => 'ssh-add -l'],
    ]);

    $this->mockQuit(0);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitSuccessException to be thrown');
    }
    catch (QuitSuccessException) {
      // Expected.
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Setting up SSH.', $output);
    }
  }

  public function testDeploySshKeyNotFound(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'aa:bb:cc:dd');
    $this->envSet('HOME', '/home/testuser');

    $this->registerMock('is_dir', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', fn(): int => 100);

    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Unable to find SSH key file', $output);
    }
  }

  public function testDeploySshCreatesSshDir(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'aa:bb:cc:dd');
    $this->envSet('HOME', '/home/testuser');

    $this->registerMock('is_dir', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $mkdir_calls = [];
    $this->registerMock('mkdir', 'DrevOps\\Eddy\\DevTools', function (string $dir) use (&$mkdir_calls): true {
      $mkdir_calls[] = $dir;
      return TRUE;
    });

    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', fn(): int => 100);

    $key_file = '/home/testuser/.ssh/id_rsa_aabbccdd';
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $file): bool => $file === $key_file);

    $this->envSet('SSH_AGENT_PID', '12345');

    $this->mockPassthruMultiple([
      ['cmd' => 'ssh-add -D'],
      ['cmd' => sprintf('ssh-add %s', escapeshellarg($key_file))],
      ['cmd' => 'ssh-add -l'],
    ]);

    $this->mockQuit(0);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitSuccessException to be thrown');
    }
    catch (QuitSuccessException) {
      // Expected.
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
    }

    $this->assertContains('/home/testuser/.ssh', $mkdir_calls);
  }

  public function testDeploySshAgentNotRunning(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'aa:bb:cc:dd');
    $this->envSet('HOME', '/home/testuser');

    $this->registerMock('is_dir', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', fn(): int => 100);

    $key_file = '/home/testuser/.ssh/id_rsa_aabbccdd';
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $file): bool => $file === $key_file);

    $this->mockPassthruMultiple([
      ['cmd' => 'eval "$(ssh-agent)"'],
      ['cmd' => 'ssh-add -D'],
      ['cmd' => sprintf('ssh-add %s', escapeshellarg($key_file))],
      ['cmd' => 'ssh-add -l'],
    ]);

    $this->mockQuit(0);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitSuccessException to be thrown');
    }
    catch (QuitSuccessException) {
      // Expected.
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Setting up SSH.', $output);
    }
  }

  public function testDeploySshSha256NoMatch(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'SHA256:nomatch');
    $this->envSet('HOME', '/home/testuser');

    $this->registerMock('is_dir', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', fn(): int => 100);

    $this->registerMock('glob', 'DrevOps\\Eddy\\DevTools', fn(): array => ['/home/testuser/.ssh/id_rsa_test']);

    $this->registerMock('exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd, ?array &$output = NULL): int {
      $output ??= [];
      if (str_contains($cmd, '-E sha256')) {
        $output[] = '2048 SHA256:differenthash testuser@host (RSA)';
      }
      return 0;
    });

    // The unconverted fingerprint cleans to 'SHA256nomatch'.
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Unable to find SSH key file', $output);
    }
  }

  public function testDeploySshSha256EmptyGlob(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'SHA256:abcdef');
    $this->envSet('HOME', '/home/testuser');

    $this->registerMock('is_dir', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', fn(): int => 100);

    $this->registerMock('glob', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    // The SHA256 fingerprint is not converted, so no key file matches.
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Unable to find SSH key file', $output);
    }
  }

  public function testDeploySshSha256EmptyExecOutput(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'SHA256:abcdef');
    $this->envSet('HOME', '/home/testuser');

    $this->registerMock('is_dir', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', fn(): int => 100);

    $this->registerMock('glob', 'DrevOps\\Eddy\\DevTools', fn(): array => ['/home/testuser/.ssh/id_rsa_test']);

    $this->registerMock('exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd, ?array &$output = NULL): int {
      $output ??= [];
      return 0;
    });

    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(): false => FALSE);

    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = ob_get_clean();
      $this->assertIsString($output);
      $this->assertStringContainsString('Unable to find SSH key file', $output);
    }
  }

  public function testDeploySshAgentPidEmpty(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');
    $this->envSet('DEPLOY_SSH_KEY_FINGERPRINT', 'aa:bb:cc:dd');
    $this->envSet('HOME', '/home/testuser');
    $this->envSet('SSH_AGENT_PID', '');

    $this->registerMock('is_dir', 'DrevOps\\Eddy\\DevTools', fn(): true => TRUE);
    $this->registerMock('file_put_contents', 'DrevOps\\Eddy\\DevTools', fn(): int => 100);

    $key_file = '/home/testuser/.ssh/id_rsa_aabbccdd';
    $this->registerMock('file_exists', 'DrevOps\\Eddy\\DevTools', fn(string $file): bool => $file === $key_file);

    $this->mockPassthruMultiple([
      ['cmd' => 'eval "$(ssh-agent)"'],
      ['cmd' => 'ssh-add -D'],
      ['cmd' => sprintf('ssh-add %s', escapeshellarg($key_file))],
      ['cmd' => 'ssh-add -l'],
    ]);

    $this->mockQuit(0);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.eddy/tooling/src/eddy-deploy';
      $this->fail('Expected QuitSuccessException to be thrown');
    }
    catch (QuitSuccessException) {
      // Expected.
    }
    finally {
      ob_get_clean();
    }
  }

}
