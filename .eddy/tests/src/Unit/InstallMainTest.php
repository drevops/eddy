<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function main;

/**
 * Tests the installation flow of 'install.php' with answers in the environment.
 *
 * Prompty takes an answer from its 'INSTALL_' variable without reading input,
 * and changes the terminal settings only when STDIN is a terminal. A child
 * process reads STDIN from a closed pipe, so every test runs in its own
 * process, where an unanswered prompt reads the end of input and cancels.
 *
 * 'INSTALL_REMOVE_SELF' is always 'false': '__FILE__' inside 'install.php'
 * resolves to the repository copy, which 'true' would delete.
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class InstallMainTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    self::envSet('SCRIPT_RUN_SKIP', '1');
    require_once self::rootDir() . '/install.php';
    self::locationsCopy(self::rootDir(), self::$sut, [], ['.idea', '.logs', '.phpunit.cache', '.artifacts', 'build']);
    chdir(self::$sut);
    self::envUnsetPrefix('INSTALL_');
  }

  /**
   * @param array<string, string> $answers
   *   The answers that replace the defaults, keyed by prompt.
   * @param array<string> $expected_present
   *   The paths expected to exist.
   * @param array<string> $expected_absent
   *   The paths expected not to exist.
   * @param array<string> $expected_summary
   *   Consecutive lines expected in the summary.
   */
  #[DataProvider('dataProviderMain')]
  public function testMain(array $answers, array $expected_present, array $expected_absent, array $expected_summary): void {
    self::envSetMultiple(self::answers($answers));

    $output = $this->capture(fn() => main(['install.php']));

    $this->assertStringContainsString('Eddy - Drupal extension scaffold', $output);
    $this->assertStringContainsString(implode(PHP_EOL, $expected_summary) . PHP_EOL, $output);

    foreach ($expected_present as $path) {
      $this->assertFileExists(self::$sut . '/' . $path);
    }

    foreach ($expected_absent as $path) {
      $this->assertFileDoesNotExist(self::$sut . '/' . $path);
    }
  }

  public static function dataProviderMain(): \Iterator {
    yield 'default answers' => [
      [],
      ['acme_widgets.info.yml', 'acme_widgets.module', '.ahoy.yml', 'phpstan.neon', 'install.php'],
      ['your_extension.info.yml', 'Makefile', 'phpunit.d10.xml', 'scripts/assemble-example.sh'],
      ['Name: Acme Widgets', 'Machine name: acme_widgets', 'Type: module', 'Drupal: 11', 'Wrapper: ahoy', 'Removed tools: None'],
    ];
    yield 'machine name derived from the kept placeholder' => [
      ['machine_name' => 'my_extension'],
      ['acme_widgets.info.yml'],
      ['my_extension.info.yml'],
      ['Name: Acme Widgets', 'Machine name: acme_widgets'],
    ];
    yield 'machine name derived from an empty answer' => [
      ['machine_name' => ''],
      ['acme_widgets.info.yml'],
      ['my_extension.info.yml'],
      ['Name: Acme Widgets', 'Machine name: acme_widgets'],
    ];
    yield 'theme with tools removed' => [
      ['machine_name' => 'acme_theme', 'type' => 'theme', 'drupal_version' => '10,11', 'command_wrapper' => 'makefile', 'tools' => 'phpcs,phpunit'],
      ['acme_theme.info.yml', 'acme_theme.theme', 'Makefile', 'phpcs.xml', 'phpunit.xml', 'phpunit.d10.xml'],
      ['acme_theme.module', '.ahoy.yml', 'phpstan.neon', 'rector.php'],
      ['Type: theme', 'Drupal: 10, 11', 'Wrapper: makefile', 'Removed tools: phpstan, rector, twigcs, eslint, stylelint, cspell, jest, functional_javascript, renovate'],
    ];
    yield 'no command wrapper' => [
      ['command_wrapper' => ''],
      ['acme_widgets.info.yml'],
      ['.ahoy.yml', 'Makefile'],
      ['Wrapper: None', 'Removed tools: None'],
    ];
    yield 'kept examples' => [
      ['examples' => 'true'],
      ['scripts/assemble-example.sh', 'scripts/provision-example.sh', 'scripts/start-example.sh', 'scripts/stop-example.sh'],
      [],
      ['Name: Acme Widgets'],
    ];
  }

  public function testMainReadsBranchesAndRepository(): void {
    self::envSetMultiple(self::answers() + ['INSTALL_BRANCHES' => '2.x,3.x', 'INSTALL_GITHUB_REPOSITORY' => 'acme/widgets']);

    $this->capture(fn() => main(['install.php']));

    $workflow = File::read(self::$sut . '/.github/workflows/test.yml');
    $this->assertStringContainsString("    branches:\n      - 2.x\n      - 3.x\n", $workflow);
    $this->assertStringNotContainsString('- main', $workflow);
    $this->assertStringContainsString('https://codecov.io/gh/acme/widgets/graph/badge.svg', File::read(self::$sut . '/README.md'));
  }

  /**
   * @param array<string, string> $answers
   *   The answers that replace the defaults, keyed by prompt.
   * @param array<string> $unanswered
   *   The prompts left without an answer.
   */
  #[DataProvider('dataProviderMainAborts')]
  public function testMainAborts(array $answers, array $unanswered, string $expected_output): void {
    self::envSetMultiple(self::answers($answers));
    self::envUnsetMultiple(array_map(static fn(string $prompt): string => 'INSTALL_' . strtoupper($prompt), $unanswered));
    $exception = NULL;
    $output = '';

    ob_start();

    try {
      main(['install.php']);
    }
    catch (\Exception $caught) {
      $exception = $caught;
    }
    finally {
      $output = (string) ob_get_clean();
    }

    $this->assertInstanceOf(\Exception::class, $exception);
    $this->assertSame('Aborting.', $exception->getMessage());
    $this->assertStringContainsString($expected_output, $output);
    $this->assertFileExists(self::$sut . '/your_extension.info.yml', 'The project is left as it was.');
    $this->assertFileDoesNotExist(self::$sut . '/acme_widgets.info.yml');
  }

  public static function dataProviderMainAborts(): \Iterator {
    yield 'installation declined' => [['proceed' => 'false'], [], 'Proceed with installation'];
    yield 'prompt cancelled' => [[], ['proceed'], 'Cancelled.'];
  }

  /**
   * Build the 'INSTALL_' variables that answer every prompt.
   *
   * @param array<string, string> $answers
   *   The answers that replace the defaults, keyed by prompt.
   *
   * @return array<string, string>
   *   The variables, keyed by name.
   */
  protected static function answers(array $answers = []): array {
    $answers += [
      'name' => 'Acme Widgets',
      'machine_name' => 'acme_widgets',
      'type' => 'module',
      'drupal_version' => '11',
      'command_wrapper' => 'ahoy',
      'tools' => 'phpcs,phpstan,rector,twigcs,eslint,stylelint,cspell,jest,phpunit,functional_javascript,renovate',
      'examples' => 'false',
      'remove_self' => 'false',
      'proceed' => 'true',
    ];

    $variables = [];

    foreach ($answers as $prompt => $answer) {
      $variables['INSTALL_' . strtoupper($prompt)] = $answer;
    }

    return $variables;
  }

}
