<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Traits\DeployWorkflowTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the deploy workflow skips deployment until a remote is set.
 *
 * Deployment is optional, so every step is gated on DEPLOY_REMOTE and a notice
 * step reports the skip. Without the gate, the SSH key step fails on the empty
 * key of every repository that has not configured deployment.
 */
#[Group('p0')]
final class DeployWorkflowConfigurationTest extends UnitTestCase {

  use DeployWorkflowTrait;

  protected const string NOTICE_STEP = 'Report missing deployment configuration';

  protected const string CONFIGURED = "\${{ env.DEPLOY_REMOTE != '' }}";

  protected const string NOT_CONFIGURED = "\${{ env.DEPLOY_REMOTE == '' }}";

  public function testRemoteIsReadIntoTheJobEnvironment(): void {
    $remote = self::deployChild(self::deployChild(self::deployJob(), 'env'), 'DEPLOY_REMOTE');

    $this->assertSame('${{ secrets.DEPLOY_REMOTE }}', $remote, 'The step conditions read DEPLOY_REMOTE, so it must be defined for the whole job.');
  }

  public function testStepsRequireRemote(): void {
    $ungated = [];

    foreach (self::deploySteps() as $step) {
      $name = self::deployChild($step, 'name');

      if ($name === self::NOTICE_STEP) {
        continue;
      }

      if (self::deployChild($step, 'if') !== self::CONFIGURED) {
        $ungated[] = is_string($name) ? $name : '(unnamed)';
      }
    }

    $this->assertSame([], $ungated, sprintf('Steps of the deploy job must run only when DEPLOY_REMOTE is set: %s', implode(', ', $ungated)));
  }

  public function testNoticeRequiresMissingRemote(): void {
    $step = self::deployStep(self::NOTICE_STEP);

    $this->assertSame(self::NOT_CONFIGURED, self::deployChild($step, 'if'), sprintf('The "%s" step must run only when DEPLOY_REMOTE is not set.', self::NOTICE_STEP));

    $script = self::deployChild($step, 'run');
    $this->assertIsString($script);
    $this->assertStringContainsString('::notice::', $script);
    $this->assertStringContainsString('DEPLOY_REMOTE', $script);
  }

}
