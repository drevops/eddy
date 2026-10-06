<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use DrevOps\Eddy\Tests\Traits\DeployWorkflowTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the commit the deploy workflow publishes.
 *
 * A `workflow_run` event resolves refs against the default branch, so a
 * checkout that names no ref holds default-branch code. The deploy script then
 * pushes that code under the triggering branch's name.
 *
 * Naming the tested commit is safe only while the job is gated on a push from
 * this repository. The deployment key is in scope for whatever is checked out.
 *
 * Neither half can be executed locally, so both are read out of the workflow.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[Group('p0')]
final class DeployWorkflowCheckoutTest extends UnitTestCase {

  use DeployWorkflowTrait;

  protected const string STEP = 'Checkout code';

  protected const string CHECKOUT_ACTION = 'actions/checkout@';

  public function testCheckoutNamesTheTestedCommit(): void {
    $ref = self::child(self::child(self::deployStep(self::STEP), 'with'), 'ref');

    $this->assertSame('${{ github.event.workflow_run.head_sha }}', $ref, sprintf('The "%s" step must check out the commit that triggered the run, otherwise the deployment publishes default-branch code.', self::STEP));
  }

  public function testEveryCheckoutNamesItsRef(): void {
    $unnamed = [];

    foreach (self::deploySteps() as $step) {
      $uses = self::child($step, 'uses');

      if (!is_string($uses)) {
        continue;
      }

      if (!str_starts_with($uses, self::CHECKOUT_ACTION)) {
        continue;
      }

      if (!is_string(self::child(self::child($step, 'with'), 'ref'))) {
        $name = self::child($step, 'name');
        $unnamed[] = is_string($name) ? $name : $uses;
      }
    }

    $this->assertSame([], $unnamed, sprintf('The deploy job checks out without naming a ref in: %s', implode(', ', $unnamed)));
  }

  public function testCheckoutFetchesFullHistory(): void {
    $fetch_depth = self::child(self::child(self::deployStep(self::STEP), 'with'), 'fetch-depth');

    $this->assertSame(0, $fetch_depth, sprintf('The "%s" step must fetch every branch and tag: the deploy step compares the commit with its branch tip, detects tag pushes and pushes every tag.', self::STEP));
  }

  #[DataProvider('dataProviderJobIsGated')]
  public function testJobIsGated(string $condition, string $message): void {
    $this->assertStringContainsString($condition, self::deployCondition(), $message);
  }

  public static function dataProviderJobIsGated(): \Iterator {
    yield 'successful run' => [
      'condition' => "github.event.workflow_run.conclusion == 'success'",
      'message' => 'A failed test run must not be deployed.',
    ];

    yield 'push event' => [
      'condition' => "github.event.workflow_run.event == 'push'",
      'message' => 'A pull request must not be deployed, otherwise unmerged code reaches the remote branch.',
    ];

    yield 'own repository' => [
      'condition' => 'github.event.workflow_run.head_repository.full_name == github.repository',
      'message' => 'A run originating from a fork must not be deployed, otherwise fork-controlled code reaches the deployment key.',
    ];
  }

  public function testConditionHasNoDisjunction(): void {
    $this->assertStringNotContainsString('||', self::deployCondition(), 'Every gate of the deploy condition must hold on its own, so the condition cannot contain a disjunction.');
  }

  /**
   * Read the condition guarding the deploy job.
   *
   * @return string
   *   The condition, with its folded whitespace collapsed.
   */
  protected static function deployCondition(): string {
    $condition = self::child(self::deployJob(), 'if');

    if (!is_string($condition)) {
      self::fail(sprintf('The deploy job of %s declares no condition.', basename(self::deployWorkflowPath())));
    }

    return trim((string) preg_replace('/\s+/', ' ', $condition));
  }

}
