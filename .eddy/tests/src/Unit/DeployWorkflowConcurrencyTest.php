<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Traits\DeployWorkflowTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that deployments of a branch run 1 at a time.
 *
 * The deploy step pushes only while its commit is the branch tip. Without a
 * shared concurrency group, a run that passed that check could still push
 * after a newer run.
 *
 * Runs join the group as their tests finish, not in push order. The default
 * queue cancels a waiting run when another one arrives, so the group keeps
 * every waiting run instead.
 *
 * GitHub enforces concurrency, so the settings are read out of the workflow.
 */
#[Group('p0')]
final class DeployWorkflowConcurrencyTest extends UnitTestCase {

  use DeployWorkflowTrait;

  #[DataProvider('dataProviderConcurrency')]
  public function testConcurrency(string $key, string|bool $expected, string $message): void {
    $concurrency = self::deployChild(self::deployJob(), 'concurrency');

    $this->assertIsArray($concurrency, 'The deploy job must declare a concurrency group.');
    $this->assertSame($expected, self::deployChild($concurrency, $key), $message);
  }

  public static function dataProviderConcurrency(): \Iterator {
    yield 'runs of a branch share a group' => [
      'key' => 'group',
      'expected' => 'deploy-${{ github.event.workflow_run.head_branch }}',
      'message' => 'Runs of a branch must share a group, otherwise another run can push between the tip check of a run and its push.',
    ];

    yield 'running deployment is not cancelled' => [
      'key' => 'cancel-in-progress',
      'expected' => FALSE,
      'message' => 'A run can arrive after a newer one, so cancelling the running deployment can leave the newest commit undeployed.',
    ];

    yield 'waiting deployments are kept' => [
      'key' => 'queue',
      'expected' => 'max',
      'message' => 'The default queue cancels a waiting run when another one arrives, and the cancelled run can hold the newest commit.',
    ];
  }

}
