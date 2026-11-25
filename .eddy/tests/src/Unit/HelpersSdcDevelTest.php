<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\log_severity_rank;
use function DrevOps\Eddy\Tooling\sdc_devel_findings;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversFunction('DrevOps\Eddy\Tooling\log_severity_rank')]
#[CoversFunction('DrevOps\Eddy\Tooling\sdc_devel_findings')]
#[Group('p0')]
final class HelpersSdcDevelTest extends UnitTestCase {

  #[DataProvider('dataProviderLogSeverityRank')]
  public function testLogSeverityRank(string $severity, ?int $expected): void {
    $this->assertSame($expected, log_severity_rank($severity));
  }

  public static function dataProviderLogSeverityRank(): \Iterator {
    yield 'emergency' => ['Emergency', 0];
    yield 'alert' => ['Alert', 1];
    yield 'critical' => ['Critical', 2];
    yield 'error' => ['Error', 3];
    yield 'warning' => ['Warning', 4];
    yield 'notice' => ['Notice', 5];
    yield 'info' => ['Info', 6];
    yield 'debug' => ['Debug', 7];
    yield 'lowercase' => ['warning', 4];
    yield 'uppercase' => ['WARNING', 4];
    yield 'surrounding spaces' => [" Error\n", 3];
    yield 'plural' => ['Errors', NULL];
    yield 'unknown' => ['Fatal', NULL];
    yield 'number' => ['4', NULL];
    yield 'empty' => ['', NULL];
  }

  /**
   * @param array<int, array<string, string>>|null $expected
   *   The findings, or NULL for a report that cannot be read.
   */
  #[DataProvider('dataProviderSdcDevelFindings')]
  public function testSdcDevelFindings(string $json, ?array $expected): void {
    $this->assertSame($expected, sdc_devel_findings($json));
  }

  public static function dataProviderSdcDevelFindings(): \Iterator {
    $finding = ['component' => 'my_theme:card', 'severity' => 'Error', 'message' => 'Unknown variable: `x`.', 'type' => 'Twig', 'line' => '3', 'source' => '{{ x }}'];

    yield 'no findings' => ['[]', []];
    yield 'finding' => ['[{"component": "my_theme:card", "severity": "Error", "message": "Unknown variable: `x`.", "type": "Twig", "line": 3, "source": "{{ x }}"}]', [$finding]];
    yield 'findings in report order' => [
      '[{"component": "my_theme:b", "severity": "Notice"}, {"component": "my_theme:a", "severity": "Error"}]',
      [
        ['component' => 'my_theme:b', 'severity' => 'Notice', 'message' => '', 'type' => '', 'line' => '', 'source' => ''],
        ['component' => 'my_theme:a', 'severity' => 'Error', 'message' => '', 'type' => '', 'line' => '', 'source' => ''],
      ],
    ];
    yield 'line without a number' => ['[{"line": "-"}]', [['component' => '', 'severity' => '', 'message' => '', 'type' => '', 'line' => '-', 'source' => '']]];
    yield 'line breaks of chunk_split() removed' => ['[{"message": "The asset library attachment woul\r\nd be more discoverable."}]', [['component' => '', 'severity' => '', 'message' => 'The asset library attachment would be more discoverable.', 'type' => '', 'line' => '', 'source' => '']]];
    yield 'other line breaks kept' => ['[{"message": "First\nSecond"}]', [['component' => '', 'severity' => '', 'message' => "First\nSecond", 'type' => '', 'line' => '', 'source' => '']]];
    yield 'non-scalar field read as empty' => ['[{"component": ["my_theme:card"], "severity": null, "line": true}]', [['component' => '', 'severity' => '', 'message' => '', 'type' => '', 'line' => '1', 'source' => '']]];
    yield 'empty string' => ['', NULL];
    yield 'invalid JSON' => ['[{"component": ', NULL];
    yield 'table output' => [" Component      Severity\n my_theme:card  Error\n", NULL];
    yield 'object' => ['{"component": "my_theme:card"}', NULL];
    yield 'list of scalars' => ['["my_theme:card"]', NULL];
    yield 'string' => ['"Error"', NULL];
  }

}
