<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

use function DrevOps\Eddy\Assets\main;

/**
 * Tests the asset generator's main flow with its commands mocked.
 *
 * A function mock takes effect only before the generator first calls the
 * function, so every test runs in its own process.
 */
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class UpdateAssetsMainTest extends UnitTestCase {

  protected const string NAMESPACE = 'DrevOps\\Eddy\\Assets';

  /**
   * The steps the mocked commands performed, in call order.
   *
   * @var list<string>
   */
  protected array $steps = [];

  /**
   * The messages written to STDERR.
   *
   * @var list<string>
   */
  protected array $stderr = [];

  /**
   * The exit code and output lines of each workspace command, keyed by command.
   *
   * @var array<string, array{0: int, 1: list<string>}>
   */
  protected array $results = [];

  /**
   * The asset whose recording exits with an error, if any.
   */
  protected ?string $failingRecording = NULL;

  protected string $assetsDir;

  protected string $projectDir;

  protected string $recordingsDir;

  protected string $workspaceDir = '';

  protected function setUp(): void {
    parent::setUp();
    self::envSet('SCRIPT_RUN_SKIP', '1');
    require_once self::rootDir() . '/.eddy/assets/update-assets.php';

    $this->assetsDir = self::$sut . '/assets';
    $this->projectDir = self::$sut . '/project';
    $this->recordingsDir = $this->projectDir . '/.artifacts/tmp/asciinema';
    File::mkdir($this->assetsDir . '/node_modules/svg-term');
    File::mkdir($this->projectDir);
  }

  /**
   * @param list<string> $only
   *   The asset names passed to main().
   * @param list<string> $expected_steps
   *   The steps expected, in order.
   * @param list<string> $expected_rendered
   *   The assets expected to be rendered.
   */
  #[DataProvider('dataProviderMain')]
  public function testMain(array $only, array $expected_steps, array $expected_rendered): void {
    $this->mockCommands();

    $output = $this->capture(fn() => main($only, $this->assetsDir, $this->projectDir));

    $this->assertSame($expected_steps, $this->steps);

    foreach (['install', 'build', 'lint', 'test'] as $name) {
      $this->assertSame(in_array($name, $expected_rendered, TRUE), File::exists($this->assetsDir . '/' . $name . '.svg'), sprintf('Rendered %s.svg.', $name));
      $this->assertSame(in_array($name, $expected_rendered, TRUE), str_contains($output, 'Rendered: ' . $name . '.svg'));
    }

    $this->assertStringContainsString('Workspace: ' . $this->workspaceDir, $output);
    $this->assertStringContainsString('Done. SVG assets updated in ' . $this->assetsDir, $output);
    $this->assertDirectoryDoesNotExist($this->workspaceDir);
    $this->assertDirectoryDoesNotExist($this->recordingsDir);
    $this->assertSame([], $this->stderr);
  }

  public static function dataProviderMain(): \Iterator {
    $install = ['record: install', 'render: install'];
    $build = ['record: build', 'render: build'];
    $lint = ['run: npm install --no-audit --no-fund', 'record: lint', 'render: lint'];
    $test = ['run: ahoy browser-start', 'record: test', 'render: test'];

    yield 'every asset' => [
      [],
      ['export', ...$install, ...$build, ...$lint, ...$test, 'run: ahoy browser-stop', 'run: ahoy stop', 'remove: workspace', 'remove: recordings'],
      ['install', 'build', 'lint', 'test'],
    ];
    yield 'the first asset' => [
      ['install'],
      ['export', ...$install, 'remove: workspace', 'remove: recordings'],
      ['install'],
    ];
    yield 'a later asset records the ones before it' => [
      ['lint'],
      ['export', 'record: install', 'record: build', ...$lint, 'run: ahoy stop', 'remove: workspace', 'remove: recordings'],
      ['lint'],
    ];
  }

  public function testMainKeepsRecordings(): void {
    self::envSet('SCRIPT_KEEP_CASTS', '1');
    $this->mockCommands();

    $output = $this->capture(fn() => main(['install'], $this->assetsDir, $this->projectDir));

    $this->assertSame(['export', 'record: install', 'render: install', 'remove: workspace'], $this->steps);
    $this->assertStringContainsString('Keeping recordings: ' . $this->recordingsDir, $output);

    foreach (['install.exp', 'install.cast', 'install.canonical.cast'] as $file) {
      $this->assertFileExists($this->recordingsDir . '/' . $file);
    }
  }

  public function testMainQuiet(): void {
    self::envSet('SCRIPT_QUIET', '1');
    $this->mockCommands();

    $output = $this->capture(fn() => main(['install'], $this->assetsDir, $this->projectDir));

    $this->assertSame('', $output);
    $this->assertFileExists($this->assetsDir . '/install.svg');
  }

  public function testMainReportsFailedCleanup(): void {
    $this->results['ahoy browser-stop'] = [1, ['No browser is running.']];
    $this->mockCommands();

    $output = $this->capture(fn() => main([], $this->assetsDir, $this->projectDir));

    $this->assertSame(["WARNING: Cleanup 'ahoy browser-stop' failed with exit code 1." . PHP_EOL . 'No browser is running.' . PHP_EOL], $this->stderr);
    $this->assertContains('run: ahoy stop', $this->steps, 'A failed cleanup does not stop the next one.');
    $this->assertStringContainsString('Done. SVG assets updated in ' . $this->assetsDir, $output);
    $this->assertDirectoryDoesNotExist($this->workspaceDir);
  }

  /**
   * @param array<string, array{0: int, 1: list<string>}> $results
   *   The exit code and output lines of each workspace command.
   * @param non-empty-string $expected_start
   *   The start of the exception message.
   * @param list<string> $expected_steps
   *   The steps expected, in order.
   */
  #[DataProvider('dataProviderMainFails')]
  public function testMainFails(array $results, ?string $failing_recording, string $expected_start, array $expected_steps): void {
    $this->results = $results;
    $this->failingRecording = $failing_recording;
    $this->mockCommands();

    try {
      $this->capture(fn() => main([], $this->assetsDir, $this->projectDir));
      $this->fail('Expected the generator to fail.');
    }
    catch (\RuntimeException $exception) {
      $this->assertStringStartsWith($expected_start, $exception->getMessage());
      $this->assertStringEndsWith(PHP_EOL . 'Recordings kept in ' . $this->recordingsDir, $exception->getMessage());
    }

    $this->assertSame($expected_steps, $this->steps);
    $this->assertDirectoryDoesNotExist($this->workspaceDir);
    $this->assertDirectoryExists($this->recordingsDir);
  }

  public static function dataProviderMainFails(): \Iterator {
    $before_lint = ['export', 'record: install', 'render: install', 'record: build', 'render: build'];

    yield 'preparing an asset fails' => [
      ['npm install --no-audit --no-fund' => [1, ['npm error code E404']]],
      NULL,
      "Preparing 'lint' failed:" . PHP_EOL . 'npm error code E404',
      [...$before_lint, 'run: npm install --no-audit --no-fund', 'run: ahoy stop', 'remove: workspace'],
    ];
    yield 'recording an asset fails' => [
      [],
      'build',
      "Recording 'build' failed. The session exited with code 1. Its last output:" . PHP_EOL . '$ ahoy build',
      ['export', 'record: install', 'render: install', 'record: build', 'run: ahoy stop', 'remove: workspace'],
    ];
  }

  /**
   * Mock the commands the generator runs, with real files in the test paths.
   */
  protected function mockCommands(): void {
    $this->mockFunction('sys_get_temp_dir', fn(): string => self::$tmp, self::NAMESPACE);

    $this->mockFunction('fwrite', function (mixed $stream, string $data): int {
      $this->stderr[] = $data;

      return strlen($data);
    }, self::NAMESPACE);

    $this->mockFunction('shell_exec', function (string $command): string {
      if (preg_match("/^which '([^']+)' 2>\\/dev\\/null$/", $command, $matches) === 1) {
        return '/usr/bin/' . $matches[1] . PHP_EOL;
      }

      if (preg_match("/^cd '([^']+)' && git archive HEAD \\| tar -x -C '([^']+)' 2>&1$/", $command, $matches) === 1) {
        $this->assertSame($this->projectDir, $matches[1]);
        $this->workspaceDir = $matches[2];
        $this->steps[] = 'export';
        File::dump($this->workspaceDir . '/install.php', '<?php');

        return '';
      }

      throw new \RuntimeException('Unexpected command: ' . $command);
    }, self::NAMESPACE);

    $this->mockFunction('exec', function (string $command, ?array &$output = NULL, ?int &$result_code = NULL): string {
      $output = [];
      $result_code = 0;

      if (preg_match("/^cd '([^']+)' && (.+) 2>&1$/", $command, $matches) === 1) {
        $this->assertSame($this->workspaceDir, $matches[1]);
        $this->steps[] = 'run: ' . $matches[2];
        [$result_code, $output] = $this->results[$matches[2]] ?? [0, []];
      }
      elseif (preg_match("/^asciinema rec .* --command='([^']+)' .* '([^']+)' 2>&1$/", $command, $matches) === 1) {
        $name = basename($matches[1], '.exp');
        $this->assertFileExists($matches[1]);
        $this->steps[] = 'record: ' . $name;
        File::dump($matches[2], self::cast(self::typedCommand($matches[1])));
        $result_code = $name === $this->failingRecording ? 1 : 0;
      }
      elseif (preg_match("/^node '[^']+' '([^']+)' '([^']+)' --line-height 1\\.1 2>&1$/", $command, $matches) === 1) {
        $this->assertFileExists($matches[1]);
        $this->steps[] = 'render: ' . basename($matches[2], '.svg');
        File::dump($matches[2], '<svg/>');
      }
      elseif (preg_match("/^rm -rf '([^']+)' 2>&1$/", $command, $matches) === 1) {
        $this->steps[] = 'remove: ' . match ($matches[1]) {
          $this->workspaceDir => 'workspace',
          $this->recordingsDir => 'recordings',
          default => throw new \RuntimeException('Unexpected directory removed: ' . $matches[1]),
        };
        File::rmdir($matches[1]);
      }
      elseif (!str_starts_with($command, 'chmod -R u+w ')) {
        throw new \RuntimeException('Unexpected command: ' . $command);
      }

      return (string) end($output);
    }, self::NAMESPACE);
  }

  /**
   * Read the command that an expect script types at the prompt.
   */
  protected static function typedCommand(string $expect_script): string {
    if (preg_match('/^type_text \{(.+)\}$/m', File::read($expect_script), $matches) !== 1) {
      throw new \RuntimeException('The expect script types no command: ' . $expect_script);
    }

    return $matches[1];
  }

  /**
   * Build a recording that opens with the prompt and the typed command.
   */
  protected static function cast(string $command): string {
    $events = [[0.5, 'o', '$ '], [0.1, 'o', $command], [0.1, 'o', "\r\n"], [0.1, 'o', "Done.\r\n"], [0.1, 'o', '$ ']];
    $lines = [json_encode(['version' => 3, 'term' => ['cols' => 80, 'rows' => 24]], JSON_THROW_ON_ERROR)];

    foreach ($events as $event) {
      $lines[] = json_encode($event, JSON_THROW_ON_ERROR);
    }

    return implode("\n", $lines) . "\n";
  }

}
