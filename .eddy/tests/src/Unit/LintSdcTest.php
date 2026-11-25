<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class LintSdcTest extends UnitTestCase {

  protected const string EXTENSION = 'my_theme';

  protected const string LOCK_WITH_SDC_DEVEL = '{"packages": [], "packages-dev": [{"name": "drupal/sdc_devel", "version": "1.1.0"}]}';

  protected const string LOG_FOUND = " [notice] Start validation of my_theme...\n [warning] Found 1 problems in my_theme.\n";

  protected function setUp(): void {
    parent::setUp();

    self::envUnset('SDC_DEVEL_FAIL_LEVEL');
  }

  /**
   * @param array<string, string> $env
   *   The environment variables to set.
   * @param array<int, string> $severities
   *   The severity of each finding in the report.
   */
  #[DataProvider('dataProviderLintSdc')]
  public function testLintSdc(array $env, string $dotenv, array $severities, ?int $expected_exit_code, string $expected_summary): void {
    self::envSetMultiple($env);
    $this->createProject(dotenv: $dotenv, report: self::report($severities));
    $this->mockPassthruMultiple(self::drushResponses());

    if ($expected_exit_code !== NULL) {
      $this->mockQuit($expected_exit_code);
    }

    $output = $this->runCommand('eddy-lint-sdc', $expected_exit_code);

    foreach ($severities as $severity) {
      $this->assertStringContainsString(sprintf('[%s] my_theme:card, line 3, Twig: %s message', $severity, $severity), $output, 'Every finding prints, whatever its severity.');
    }

    $this->assertStringEndsWith($expected_summary . "\n", $output);
  }

  public static function dataProviderLintSdc(): \Iterator {
    // The default level fails on 'warning' and every more severe finding.
    yield 'emergency fails by default' => [[], '', ['Emergency'], 1, '[FAIL] Findings at or above "warning": 1 of 1.'];
    yield 'alert fails by default' => [[], '', ['Alert'], 1, '[FAIL] Findings at or above "warning": 1 of 1.'];
    yield 'critical fails by default' => [[], '', ['Critical'], 1, '[FAIL] Findings at or above "warning": 1 of 1.'];
    yield 'error fails by default' => [[], '', ['Error'], 1, '[FAIL] Findings at or above "warning": 1 of 1.'];
    yield 'warning fails by default' => [[], '', ['Warning'], 1, '[FAIL] Findings at or above "warning": 1 of 1.'];
    yield 'notice passes by default' => [[], '', ['Notice'], NULL, '[ OK ] Findings at or above "warning": 0 of 1.'];
    yield 'info passes by default' => [[], '', ['Info'], NULL, '[ OK ] Findings at or above "warning": 0 of 1.'];
    yield 'debug passes by default' => [[], '', ['Debug'], NULL, '[ OK ] Findings at or above "warning": 0 of 1.'];
    yield 'unknown severity fails' => [[], '', ['Fatal'], 1, '[FAIL] Findings at or above "warning": 1 of 1.'];
    yield 'empty severity fails' => [[], '', [''], 1, '[FAIL] Findings at or above "warning": 1 of 1.'];
    yield 'no findings pass' => [[], '', [], NULL, '[ OK ] Findings at or above "warning": 0 of 0.'];
    yield 'mixed findings count the failing ones' => [[], '', ['Notice', 'Warning', 'Info', 'Error'], 1, '[FAIL] Findings at or above "warning": 2 of 4.'];
    yield 'lowercase severity' => [[], '', ['warning'], 1, '[FAIL] Findings at or above "warning": 1 of 1.'];

    // A level from the environment moves the threshold both ways.
    yield 'error level passes a warning' => [['SDC_DEVEL_FAIL_LEVEL' => 'error'], '', ['Warning'], NULL, '[ OK ] Findings at or above "error": 0 of 1.'];
    yield 'error level fails an error' => [['SDC_DEVEL_FAIL_LEVEL' => 'error'], '', ['Error'], 1, '[FAIL] Findings at or above "error": 1 of 1.'];
    yield 'emergency level passes an alert' => [['SDC_DEVEL_FAIL_LEVEL' => 'emergency'], '', ['Alert', 'Critical'], NULL, '[ OK ] Findings at or above "emergency": 0 of 2.'];
    yield 'emergency level fails an emergency' => [['SDC_DEVEL_FAIL_LEVEL' => 'emergency'], '', ['Emergency'], 1, '[FAIL] Findings at or above "emergency": 1 of 1.'];
    yield 'notice level fails a notice' => [['SDC_DEVEL_FAIL_LEVEL' => 'notice'], '', ['Notice', 'Info'], 1, '[FAIL] Findings at or above "notice": 1 of 2.'];
    yield 'debug level fails a debug' => [['SDC_DEVEL_FAIL_LEVEL' => 'debug'], '', ['Debug'], 1, '[FAIL] Findings at or above "debug": 1 of 1.'];
    yield 'empty level is the default' => [['SDC_DEVEL_FAIL_LEVEL' => ''], '', ['Warning'], 1, '[FAIL] Findings at or above "warning": 1 of 1.'];
    yield 'level in any letter case' => [['SDC_DEVEL_FAIL_LEVEL' => ' Critical '], '', ['Error'], NULL, '[ OK ] Findings at or above "critical": 0 of 1.'];
    yield 'level from .env' => [[], "SDC_DEVEL_FAIL_LEVEL=info\n", ['Info'], 1, '[FAIL] Findings at or above "info": 1 of 1.'];
    yield 'level from the environment over .env' => [['SDC_DEVEL_FAIL_LEVEL' => 'critical'], "SDC_DEVEL_FAIL_LEVEL=info\n", ['Info'], NULL, '[ OK ] Findings at or above "critical": 0 of 1.'];
  }

  public function testLintSdcPrintsFindings(): void {
    $report = json_encode([
      ['component' => 'my_theme:card', 'severity' => 'Error', 'message' => 'Unused variables: unused_prop', 'type' => 'Twig', 'line' => '-', 'source' => ''],
      ['component' => 'my_theme:card', 'severity' => 'Error', 'message' => "Forbidden Twig function: `attach_library`. The asset library attachment woul\r\nd be more discoverable.", 'type' => 'Twig', 'line' => 6, 'source' => "{{ attach_library('core/drupal') }}"],
      ['component' => 'my_theme:hero', 'severity' => 'Notice', 'message' => 'Ternary test with boolean result', 'type' => 'Twig', 'line' => 1, 'source' => '{{ title ? true : false }}'],
    ], JSON_THROW_ON_ERROR);
    $this->createProject(report: $report);
    $this->mockPassthruMultiple(self::drushResponses());
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-lint-sdc', 1);

    $expected = implode("\n", [
      '[TASK] Validating Single Directory Components of my_theme.',
      '       [Error] my_theme:card, line -, Twig: Unused variables: unused_prop',
      '       [Error] my_theme:card, line 6, Twig: Forbidden Twig function: `attach_library`. The asset library attachment would be more discoverable.',
      "         {{ attach_library('core/drupal') }}",
      '       [Notice] my_theme:hero, line 1, Twig: Ternary test with boolean result',
      '         {{ title ? true : false }}',
      '[FAIL] Findings at or above "warning": 2 of 3.',
    ]) . "\n";
    $this->assertSame($expected, $output);
  }

  public function testLintSdcSkipsWithoutComponents(): void {
    $this->createProject(components: FALSE, lock: '');
    $this->mockPassthruNever();
    $this->mockQuit(0);

    $output = $this->runCommand('eddy-lint-sdc', 0);

    $this->assertStringContainsString('[ OK ] No components directory, so there is nothing to validate.', $output);
  }

  #[DataProvider('dataProviderLintSdcFailsWithoutPackage')]
  public function testLintSdcFailsWithoutPackage(string $lock): void {
    $this->createProject(lock: $lock);
    $this->mockPassthruNever();
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-lint-sdc', 1);

    $this->assertStringContainsString('[FAIL] The build does not install drupal/sdc_devel. Add it to "require-dev" in composer.dev.json, then run assemble again.', $output);
  }

  public static function dataProviderLintSdcFailsWithoutPackage(): \Iterator {
    yield 'no build' => [''];
    yield 'other packages' => ['{"packages": [{"name": "drupal/core", "version": "11.2.0"}], "packages-dev": []}'];
  }

  #[DataProvider('dataProviderLintSdcFailsOnInvalidLevel')]
  public function testLintSdcFailsOnInvalidLevel(string $level, string $expected_level): void {
    self::envSet('SDC_DEVEL_FAIL_LEVEL', $level);
    $this->createProject();
    $this->mockPassthruNever();
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-lint-sdc', 1);

    $this->assertSame(sprintf('[FAIL] Invalid SDC_DEVEL_FAIL_LEVEL "%s". Use one of: emergency, alert, critical, error, warning, notice, info, debug.', $expected_level) . "\n", $output);
  }

  public static function dataProviderLintSdcFailsOnInvalidLevel(): \Iterator {
    yield 'unknown name' => ['loud', 'loud'];
    yield 'number' => ['4', '4'];
    yield 'blank' => ['   ', ''];
  }

  public function testLintSdcFailsWhenDrushFails(): void {
    $this->createProject(log: " [error]  Drush was unable to query the database.\n");
    $this->mockPassthruMultiple(self::drushResponses(1));
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-lint-sdc', 1);

    $this->assertStringContainsString(" [error]  Drush was unable to query the database.\n[FAIL] Drush command failed: sdc-devel:validate.", $output);
  }

  public function testLintSdcFailsWhenNoComponentsFound(): void {
    $log = " [notice] Start validation of my_theme...\n [warning] No components found, either it contains no components or is not enabled.\n";
    $this->createProject(report: '[]', log: $log);
    $this->mockPassthruMultiple(self::drushResponses());
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-lint-sdc', 1);

    $this->assertStringContainsString($log . '[FAIL] sdc_devel found no components of my_theme. Run assemble and provision, then run this command again.', $output);
  }

  #[DataProvider('dataProviderLintSdcFailsOnUnreadableReport')]
  public function testLintSdcFailsOnUnreadableReport(?string $report): void {
    $this->createProject(report: $report);
    $this->mockPassthruMultiple(self::drushResponses());
    $this->mockQuit(1);

    $output = $this->runCommand('eddy-lint-sdc', 1);

    $this->assertStringContainsString('[FAIL] Unable to read the sdc_devel report in .logs/sdc-devel.json.', $output);
  }

  public static function dataProviderLintSdcFailsOnUnreadableReport(): \Iterator {
    yield 'missing report' => [NULL];
    yield 'empty report' => [''];
    yield 'table instead of JSON' => [' Component  Severity  Message'];
    yield 'object instead of a list' => ['{"component": "my_theme:card"}'];
  }

  /**
   * Create a project in a temporary directory and change into it.
   *
   * @param bool $components
   *   Whether the project has a 'components' directory.
   * @param string $lock
   *   The content of 'build/composer.lock', or an empty string for none.
   * @param string $dotenv
   *   The content of '.env', or an empty string for none.
   * @param string|null $report
   *   The report that the mocked Drush leaves in '.logs/sdc-devel.json', or
   *   NULL for none.
   * @param string $log
   *   The log that the mocked Drush leaves in '.logs/sdc-devel.log'.
   */
  protected function createProject(bool $components = TRUE, string $lock = self::LOCK_WITH_SDC_DEVEL, string $dotenv = '', ?string $report = '[]', string $log = self::LOG_FOUND): void {
    $dir = self::$tmp . '/lint_sdc_' . uniqid();
    File::dump($dir . '/' . self::EXTENSION . '.info.yml', "name: My Theme\ntype: theme\n");

    if ($components) {
      File::dump($dir . '/components/card/card.component.yml', "name: Card\n");
    }

    if ($lock !== '') {
      File::dump($dir . '/build/composer.lock', $lock);
    }

    if ($dotenv !== '') {
      File::dump($dir . '/.env', $dotenv);
    }

    // The mocked passthru() runs no shell, so the files that Drush would write
    // through the redirections exist beforehand.
    if ($report !== NULL) {
      File::dump($dir . '/.logs/sdc-devel.json', $report);
    }

    File::dump($dir . '/.logs/sdc-devel.log', $log);

    chdir($dir);
  }

  /**
   * Build a report with 1 finding of each severity.
   *
   * @param array<int, string> $severities
   *   The severities, in report order.
   */
  protected static function report(array $severities): string {
    $rows = array_map(static fn(string $severity): array => [
      'component' => 'my_theme:card',
      'severity' => $severity,
      'message' => $severity . ' message',
      'type' => 'Twig',
      'line' => 3,
      'source' => '{{ title }}',
    ], $severities);

    return json_encode($rows, JSON_THROW_ON_ERROR);
  }

  /**
   * @return array<int, array{cmd: string, result_code: int}>
   *   The Drush commands that enable 'sdc_devel' and validate, in order.
   */
  protected static function drushResponses(int $validate_result_code = 0): array {
    $prefix = 'build/vendor/bin/drush -r ' . self::shellQuote(File::cwd() . '/build/web') . ' -y ';

    return [
      ['cmd' => $prefix . 'pm:enable sdc_devel', 'result_code' => 0],
      ['cmd' => $prefix . "sdc-devel:validate 'my_theme' --format=json > '.logs/sdc-devel.json' 2> '.logs/sdc-devel.log'", 'result_code' => $validate_result_code],
    ];
  }

}
