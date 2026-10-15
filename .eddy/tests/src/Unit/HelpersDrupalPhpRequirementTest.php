<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\drupal_php_requirement;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\drupal_php_requirement')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersDrupalPhpRequirementTest extends UnitTestCase {

  /**
   * @param array<int, string> $output
   */
  #[DataProvider('dataProviderDrupalPhpRequirement')]
  public function testDrupalPhpRequirement(array $output, int $exit_code, ?string $expected): void {
    $this->mockExecOutput(self::composerShowCommand('drupal/core', '12.0.0-beta1'), $output, $exit_code);

    $this->assertSame($expected, drupal_php_requirement('12.0.0-beta1'));
  }

  public static function dataProviderDrupalPhpRequirement(): \Iterator {
    $package = static fn(mixed $requires): array => [json_encode(['name' => 'drupal/core', 'requires' => $requires], JSON_THROW_ON_ERROR)];

    yield 'requirement' => [$package(['php' => '>=8.5', 'symfony/console' => '^8.0']), 0, '>=8.5'];
    yield 'no PHP requirement' => [$package(['symfony/console' => '^8.0']), 0, NULL];
    yield 'empty PHP requirement' => [$package(['php' => '']), 0, NULL];
    yield 'PHP requirement that is not a string' => [$package(['php' => ['>=8.5']]), 0, NULL];
    yield 'no requirements' => [['{"name": "drupal/core"}'], 0, NULL];
    yield 'composer fails' => [[], 1, NULL];
  }

}
