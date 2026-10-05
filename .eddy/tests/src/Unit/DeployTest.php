<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use DrevOps\Eddy\Tests\Exceptions\QuitSuccessException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the deploy devtools script.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class DeployTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.devtools/helpers.php';
  }

  public function testDeploySkipWhenProceedNotSet(): void {
    $this->envSet('DEPLOY_USER_NAME', 'Test User');
    $this->envSet('DEPLOY_USER_EMAIL', 'test@example.com');
    $this->envSet('DEPLOY_REMOTE', 'git@git.drupal.org:project/test.git');

    $this->mockQuit(0);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.devtools/deploy';
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
      require dirname(__DIR__, 4) . '/.devtools/deploy';
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
    $passthru_responses[] = ['cmd' => sprintf('git push --force deployremote HEAD:%s', escapeshellarg($effective_branch))];

    $passthru_responses[] = ['cmd' => 'git push --force --tags deployremote 2>/dev/null || true'];

    $this->mockPassthruMultiple($passthru_responses);

    ob_start();
    require dirname(__DIR__, 4) . '/.devtools/deploy';
    $output = ob_get_clean();

    $this->assertIsString($output);
    $this->assertStringContainsString('DEPLOY', $output);
    $this->assertStringContainsString('Pushing code to branch ' . $effective_branch, $output);
    $this->assertStringContainsString('Code pushed to ' . $deploy_remote . ':' . $effective_branch, $output);
    $this->assertStringContainsString('Tags pushed to ' . $deploy_remote, $output);
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

  public function testDeployMissingRequiredVars(): void {
    $this->mockQuit(1);

    ob_start();
    try {
      require dirname(__DIR__, 4) . '/.devtools/deploy';
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

}
