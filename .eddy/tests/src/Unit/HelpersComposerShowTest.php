<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\composer_show;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the composer_show() helper.
 */
#[CoversFunction('DrevOps\Eddy\Tooling\composer_show')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersComposerShowTest extends UnitTestCase {

  /**
   * @param array<int, string> $output
   */
  #[DataProvider('dataProviderComposerShow')]
  public function testComposerShow(string $package, string $version, string $expected_command, array $output, int $exit_code, ?array $expected): void {
    $this->mockFunction('exec', function (string $command, ?array &$exec_output = NULL, ?int &$result_code = NULL) use ($expected_command, $output, $exit_code): string {
      $this->assertSame($expected_command, $command);
      $exec_output = $output;
      $result_code = $exit_code;

      return '';
    });

    $this->assertSame($expected, composer_show($package, $version));
  }

  public static function dataProviderComposerShow(): \Iterator {
    $package = ['name' => 'drupal/core', 'requires' => ['php' => '>=8.3.0']];
    $lines = explode("\n", json_encode($package, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    yield 'package' => ['drupal/recommended-project', '', "composer show --all --format=json 'drupal/recommended-project'", ['{"versions": ["11.4.8"]}'], 0, ['versions' => ['11.4.8']]];
    yield 'version' => ['drupal/core', '11.4.8', "composer show --all --format=json 'drupal/core' '11.4.8'", $lines, 0, $package];
    yield 'composer fails' => ['drupal/core', '13.0.0', "composer show --all --format=json 'drupal/core' '13.0.0'", [], 1, NULL];
    yield 'output with a failure exit code' => ['drupal/core', '11.4.8', "composer show --all --format=json 'drupal/core' '11.4.8'", $lines, 1, NULL];
    yield 'output is not JSON' => ['drupal/core', '11.4.8', "composer show --all --format=json 'drupal/core' '11.4.8'", ['Not JSON'], 0, NULL];
    yield 'output is not a JSON object' => ['drupal/core', '11.4.8', "composer show --all --format=json 'drupal/core' '11.4.8'", ['"drupal/core"'], 0, NULL];
  }

}
