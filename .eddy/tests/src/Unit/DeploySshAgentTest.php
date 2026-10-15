<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;

/**
 * Tests that the deploy script loads the key into an SSH agent it starts.
 *
 * The script runs against a real 'ssh-agent', with no agent in its environment
 * and a generated key named by its fingerprint, as a CI that installs keys by
 * fingerprint leaves it. It stops before the push.
 */
#[Group('p0')]
final class DeploySshAgentTest extends UnitTestCase {

  /**
   * The home directory of the script, relative to its working directory.
   */
  protected const string HOME = 'home';

  /**
   * The process ID of the agent that the script started.
   */
  protected ?int $agentPid = NULL;

  protected function tearDown(): void {
    // The script leaves the agent it starts running.
    if ($this->agentPid !== NULL) {
      (new Process(['kill', (string) $this->agentPid]))->run();
    }

    parent::tearDown();
  }

  #[DataProvider('dataProviderAgentStarted')]
  public function testAgentStarted(string $algorithm): void {
    $key = $this->createKey();

    $process = $this->runDeploy($key[$algorithm]);
    $output = $process->getOutput();

    if (preg_match('/Started an SSH agent with PID (\d+)\./', $output, $matches) === 1) {
      $this->agentPid = (int) $matches[1];
    }

    $this->assertTrue($process->isSuccessful(), $output . $process->getErrorOutput());
    $this->assertNotNull($this->agentPid, 'The script reports the agent it starts.');
    $this->assertStringContainsString(sprintf('SSH key %s added.', $key['file']), $output);
    // 'ssh-add -l' lists the key only when it reaches the agent.
    $this->assertStringContainsString($key['sha256'], $output);
  }

  public static function dataProviderAgentStarted(): \Iterator {
    yield 'MD5 fingerprint' => ['md5'];
    yield 'SHA256 fingerprint' => ['sha256'];
  }

  /**
   * Generate a key in the home directory, named by its MD5 fingerprint.
   *
   * @return array{file: string, md5: string, sha256: string}
   *   The key file relative to the working directory, the MD5 fingerprint as
   *   colon-separated hex, and the SHA256 fingerprint with its 'SHA256:'
   *   prefix.
   */
  protected function createKey(): array {
    File::mkdir(self::$tmp . '/' . self::HOME . '/.ssh', 0700);

    $generated = self::HOME . '/.ssh/generated';
    $this->command(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-C', 'eddy-deploy-test', '-f', $generated]);

    $md5 = explode(' ', $this->command(['ssh-keygen', '-l', '-E', 'md5', '-f', $generated]))[1] ?? '';
    $sha256 = explode(' ', $this->command(['ssh-keygen', '-l', '-E', 'sha256', '-f', $generated]))[1] ?? '';
    $md5 = str_replace('MD5:', '', $md5);

    $file = self::HOME . '/.ssh/id_rsa_' . str_replace(':', '', $md5);
    rename(self::$tmp . '/' . $generated, self::$tmp . '/' . $file);
    rename(self::$tmp . '/' . $generated . '.pub', self::$tmp . '/' . $file . '.pub');

    return ['file' => $file, 'md5' => $md5, 'sha256' => $sha256];
  }

  /**
   * Run the deploy script in the working directory.
   *
   * @param string $fingerprint
   *   The fingerprint of the key to load.
   */
  protected function runDeploy(string $fingerprint): Process {
    $process = new Process([PHP_BINARY, self::toolingDir() . '/src/eddy-deploy'], self::$tmp, [
      // OpenSSH 10.1 and later create the agent socket under the home
      // directory, so a relative one keeps the socket path within its limit.
      'HOME' => self::HOME,
      'DEPLOY_USER_NAME' => 'Deploy Bot',
      'DEPLOY_USER_EMAIL' => 'deploy@example.com',
      'DEPLOY_REMOTE' => 'git@example.com:project/deploy.git',
      'DEPLOY_PROCEED' => '0',
      'DEPLOY_SSH_KEY_FINGERPRINT' => $fingerprint,
      // The script would reuse an agent of the runner and clear its keys.
      'SSH_AUTH_SOCK' => FALSE,
      'SSH_AGENT_PID' => FALSE,
      'DEBUG' => '',
      'TERM' => 'dumb',
    ]);
    $process->run();

    return $process;
  }

  /**
   * Run a command in the working directory, or fail the test.
   *
   * @param list<string> $command
   *   The executable followed by its arguments.
   *
   * @return string
   *   The standard output, without surrounding whitespace.
   */
  protected function command(array $command): string {
    $process = new Process($command, self::$tmp);
    $process->run();

    if (!$process->isSuccessful()) {
      self::fail(sprintf("'%s' failed:\n%s", implode(' ', $command), $process->getErrorOutput()));
    }

    return trim($process->getOutput());
  }

}
