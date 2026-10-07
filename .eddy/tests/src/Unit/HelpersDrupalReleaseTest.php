<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\drupal_release;
use DrevOps\Eddy\Tests\Exceptions\QuitErrorException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the drupal_release() helper.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\drupal_release')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersDrupalReleaseTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  /**
   * Mock the Composer call that lists the Drupal releases.
   *
   * @param array<int, string> $output
   *   The lines Composer prints to stdout.
   * @param int $exit_code
   *   The exit code Composer returns.
   */
  protected function mockReleaseList(array $output, int $exit_code = 0): void {
    $this->registerMock('exec', 'DrevOps\\Eddy\\DevTools', function (string $cmd, ?array &$exec_output = NULL, ?int &$code = NULL) use ($output, $exit_code): string {
      $this->assertSame('composer show --all --format=json drupal/recommended-project', $cmd);
      $exec_output = $output;
      $code = $exit_code;

      return '';
    });
  }

  public function testDrupalRelease(): void {
    $this->mockReleaseList(explode("\n", json_encode(['name' => 'drupal/recommended-project', 'versions' => ['12.0.0-beta1', '11.x-dev', '11.4.8', '11.4.7']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)));

    $this->assertSame('11.4.8', drupal_release('11'));
  }

  /**
   * @param array<int, string> $output
   */
  #[DataProvider('dataProviderDrupalReleaseFailure')]
  public function testDrupalReleaseFailure(string $constraint, array $output, int $exit_code, string $expected_message): void {
    $this->mockReleaseList($output, $exit_code);
    $this->mockQuit(1);

    ob_start();
    try {
      drupal_release($constraint);
      $this->fail('Expected QuitErrorException to be thrown.');
    }
    catch (QuitErrorException $e) {
      $this->assertSame(1, $e->getCode());
    }
    finally {
      $output = (string) ob_get_clean();
    }

    $this->assertStringContainsString($expected_message, $output);
  }

  public static function dataProviderDrupalReleaseFailure(): \Iterator {
    $versions = json_encode(['versions' => ['11.4.8']], JSON_THROW_ON_ERROR);

    yield 'composer fails' => ['11', [], 1, 'Unable to list the Drupal releases.'];
    yield 'output is not JSON' => ['11', ['Not JSON'], 0, 'Unable to list the Drupal releases.'];
    yield 'output has no versions' => ['11', ['{"name": "drupal/recommended-project"}'], 0, 'Unable to list the Drupal releases.'];
    yield 'no release matches' => ['13', [$versions], 0, 'No Drupal release matches 13.'];
  }

}
