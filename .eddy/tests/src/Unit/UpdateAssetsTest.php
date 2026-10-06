<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

use function DrevOps\Eddy\Assets\arrival_at;
use function DrevOps\Eddy\Assets\canonicalize_cast;
use function DrevOps\Eddy\Assets\create_expect_script;
use function DrevOps\Eddy\Assets\get_jobs;
use function DrevOps\Eddy\Assets\init_steps;
use function DrevOps\Eddy\Assets\join_lines;
use function DrevOps\Eddy\Assets\path_replacements;
use function DrevOps\Eddy\Assets\read_cast;
use function DrevOps\Eddy\Assets\remove_dir;
use function DrevOps\Eddy\Assets\resolve_jobs;
use function DrevOps\Eddy\Assets\sanitize_output;
use function DrevOps\Eddy\Assets\session_tail;
use function DrevOps\Eddy\Assets\split_lines;
use function DrevOps\Eddy\Assets\split_redraws;

use const DrevOps\Eddy\Assets\END_PAUSE;
use const DrevOps\Eddy\Assets\FRAME_DELAY;
use const DrevOps\Eddy\Assets\STEP_DELAY;

/**
 * Unit tests for the asset generator in '.eddy/assets/update-assets.php'.
 */
#[Group('p0')]
final class UpdateAssetsTest extends UnitTestCase {

  protected ?string $originalHome = NULL;

  public static function setUpBeforeClass(): void {
    putenv('SCRIPT_RUN_SKIP=1');
    require_once dirname(__DIR__, 4) . '/.eddy/assets/update-assets.php';
    parent::setUpBeforeClass();
  }

  protected function setUp(): void {
    parent::setUp();
    $home = getenv('HOME');
    $this->originalHome = $home === FALSE ? NULL : $home;
  }

  protected function tearDown(): void {
    putenv($this->originalHome === NULL ? 'HOME' : 'HOME=' . $this->originalHome);
    parent::tearDown();
  }

  /**
   * @param list<array{0: float|int, 1: string, 2: string}> $first
   *   Events of the first recording.
   * @param list<array{0: float|int, 1: string, 2: string}> $second
   *   Events of the second recording of the same session.
   */
  #[DataProvider('dataProviderCanonicalizeCastIsReproducible')]
  public function testCanonicalizeCastIsReproducible(string $command, string $frames, array $first, array $second): void {
    $this->assertSame(canonicalize_cast(self::cast($first), $command, $frames), canonicalize_cast(self::cast($second), $command, $frames));
  }

  public static function dataProviderCanonicalizeCastIsReproducible(): \Iterator {
    yield 'lines, chunking and timing differ' => [
      'ahoy lint',
      'lines',
      [
        [0.5, 'o', "\e[?2004h$ "],
        [2.0, 'o', 'ahoy lint'],
        [1.0, 'o', "\r\r\n\e[?2004l\r"],
        [0.3, 'o', "Running PHPCS\r\r\n.."],
        [0.2, 'o', ". 3 / 3 (100%)\r\r\nTime: 230ms; Memory: 18MB\r\r\n"],
        [0.4, 'o', "\e[?2004h$ "],
        [0.003, 'x', '0'],
      ],
      [
        [0.4, 'o', "\e[?2004h"],
        [0.01, 'o', '$ '],
        [3.5, 'o', 'ahoy'],
        [0.001, 'o', " lint\r\r\n"],
        [1.2, 'o', "\e[?2004l\rRunning PHPCS\r\r\n"],
        [5.0, 'o', '.'],
        [0.9, 'o', '.'],
        [0.9, 'o', ". 3 / 3 (100%)\r\r\n"],
        [0.1, 'o', "Time: 916ms; Memory: 18MB\r\r\n\e[?2004h$ "],
        [0.003, 'x', '0'],
      ],
    ];

    yield 'redraws, chunking and timing differ within the same steps' => [
      'php init.php',
      'redraws',
      [
        [0.5, 'o', "\e[?2004h$ "],
        [2.2, 'o', 'php init.php'],
        [1.0, 'o', "\r\r\n\e[?2004l\r"],
        [0.1, 'o', "Name\r\r\n> _\r\r\n"],
        [2.4, 'o', "\e[2A\r\e[JName\r\r\n> a\r\r\n\e[2A\r\e[JName\r\r\n> ab\r\r\n"],
        [1.0, 'o', "\e[2A\r\e[JName: ab\r\r\n"],
        [1.3, 'o', "\e[?2004h$ "],
      ],
      [
        [0.6, 'o', "\e[?2004h$ php"],
        [0.001, 'o', ' init.php'],
        [1.1, 'o', "\r\r\n"],
        [0.001, 'o', "\e[?2004l\rName\r\r\n"],
        [0.3, 'o', "> _\r\r\n"],
        [2.0, 'o', "\e[2A\r\e[JName\r\r\n> a"],
        [0.01, 'o', "\r\r\n\e[2A\r\e[JName\r\r\n> ab\r\r\n"],
        [3.0, 'o', "\e[2A\r\e[JName: ab\r\r\n\e[?2004h$ "],
      ],
    ];
  }

  #[DataProvider('dataProviderCanonicalizeCastIsReproducibleForRecordings')]
  public function testCanonicalizeCastIsReproducibleForRecordings(string $name): void {
    $job = get_jobs()[$name];
    $canonical = [];

    // The fixtures are 2 real recordings of each session, made by back-to-back
    // regenerations in workspaces with different names.
    foreach (['first' => '111111111111', 'second' => '222222222222'] as $run => $id) {
      $workspace_dir = '/var/folders/aa/T/eddy-assets-' . $id;
      $replacements = [
        $workspace_dir => '/home/user/project',
        '/private' . $workspace_dir => '/home/user/project',
        '/Users/maintainer' => '/home/user',
      ];

      $content = (string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/assets/' . $run . '/' . $name . '.cast');
      $canonical[$run] = canonicalize_cast($content, $job['command'], $job['frames'], $replacements);
    }

    $this->assertSame($canonical['first'], $canonical['second']);
    $this->assertStringNotContainsString('eddy-assets-', $canonical['first']);
    $this->assertStringNotContainsString('/Users/maintainer', $canonical['first']);
  }

  public static function dataProviderCanonicalizeCastIsReproducibleForRecordings(): \Iterator {
    yield 'init' => ['init'];
    yield 'build' => ['build'];
    yield 'lint' => ['lint'];
    yield 'test' => ['test'];
  }

  /**
   * @param list<array{0: float|int, 1: string, 2: string}> $events
   *   Events of the recording.
   * @param list<array{0: string, 1: string}> $expected
   *   Frames of the canonical recording: the delay before the frame ('start',
   *   'step', 'frame' or 'end') and its output.
   */
  #[DataProvider('dataProviderCanonicalizeCast')]
  public function testCanonicalizeCast(string $command, string $frames, array $events, array $expected): void {
    $delays = ['start' => 0.0, 'step' => STEP_DELAY, 'frame' => FRAME_DELAY, 'end' => END_PAUSE];
    $expected_events = array_map(static fn(array $frame): array => [$delays[$frame[0]], 'o', $frame[1]], $expected);

    $this->assertSame(self::canonical($expected_events), canonicalize_cast(self::cast($events), $command, $frames));
  }

  public static function dataProviderCanonicalizeCast(): \Iterator {
    yield 'lines' => [
      'ls',
      'lines',
      [
        [0.5, 'o', '$ '],
        [2.0, 'o', 'ls'],
        [1.0, 'o', "\r\nfirst\r\nsecond\r\n"],
        [3.0, 'o', "third\r\n$ "],
      ],
      [
        ['start', '$ '],
        ['step', 'l'],
        ['frame', 's'],
        ['step', "\r\n"],
        ['frame', "first\r\n"],
        ['frame', "second\r\n"],
        ['frame', "third\r\n"],
        ['frame', '$ '],
        ['end', ' '],
      ],
    ];

    yield 'redraws, a burst continues the step and a gap starts the next' => [
      'ls',
      'redraws',
      [
        [0.5, 'o', '$ '],
        [2.0, 'o', 'ls'],
        [1.0, 'o', "\r\nA\r\n"],
        [2.0, 'o', "\e[1AB\r\n"],
        [0.2, 'o', "\e[1AC\r\n"],
        [0.4, 'o', "\e[1AD\r\n"],
        [0.6, 'o', "\e[1AE\r\n$ "],
      ],
      [
        ['start', '$ '],
        ['step', 'l'],
        ['frame', 's'],
        ['step', "\r\nA\r\n"],
        ['step', "\e[1AB\r\n"],
        ['frame', "\e[1AC\r\n"],
        ['frame', "\e[1AD\r\n"],
        ['step', "\e[1AE\r\n$ "],
        ['end', ' '],
      ],
    ];

    yield 'redraws, events that draw nothing still take time' => [
      'ls',
      'redraws',
      [
        [0.5, 'o', '$ '],
        [2.0, 'o', 'ls'],
        [1.0, 'o', "\r\nA\r\n"],
        [0.3, 'r', '80x24'],
        [0.3, 'o', "\e[1AB\r\n"],
      ],
      [
        ['start', '$ '],
        ['step', 'l'],
        ['frame', 's'],
        ['step', "\r\nA\r\n"],
        ['step', "\e[1AB\r\n"],
        ['end', ' '],
      ],
    ];

    yield 'output before the prompt stays in the first frame' => [
      'ls',
      'lines',
      [
        [0.5, 'o', "Banner\r\n$ "],
        [2.0, 'o', 'ls'],
        [1.0, 'o', "\r\n$ "],
      ],
      [
        ['start', "Banner\r\n$ "],
        ['step', 'l'],
        ['frame', 's'],
        ['step', "\r\n"],
        ['frame', '$ '],
        ['end', ' '],
      ],
    ];

    yield 'volatile values are masked' => [
      'ls',
      'lines',
      [
        [0.5, 'o', '$ '],
        [2.0, 'o', 'ls'],
        [1.0, 'o', "\r\nTime: 916ms; Memory: 18MB\r\n"],
      ],
      [
        ['start', '$ '],
        ['step', 'l'],
        ['frame', 's'],
        ['step', "\r\n"],
        ['frame', "Time: 230ms; Memory: 18MB\r\n"],
        ['end', ' '],
      ],
    ];
  }

  public function testCanonicalizeCastReplacesPathsSplitAcrossEvents(): void {
    $events = [
      [0.5, 'o', '$ '],
      [2.0, 'o', 'ls'],
      [1.0, 'o', "\r\nDirectory : /var/fold"],
      [0.1, 'o', "ers/ws/build/web\r\n"],
    ];

    $canonical = canonicalize_cast(self::cast($events), 'ls', 'lines', ['/var/folders/ws' => '/home/user/project']);

    $this->assertStringContainsString('Directory : /home/user/project/build/web', $canonical);
    $this->assertStringNotContainsString('/var/folders', $canonical);
  }

  /**
   * @param class-string<\Throwable> $exception
   *   The expected exception class.
   */
  #[DataProvider('dataProviderCanonicalizeCastRejectsRecording')]
  public function testCanonicalizeCastRejectsRecording(string $cast, string $command, string $frames, string $exception, string $message): void {
    $this->expectException($exception);
    $this->expectExceptionMessage($message);

    canonicalize_cast($cast, $command, $frames);
  }

  public static function dataProviderCanonicalizeCastRejectsRecording(): \Iterator {
    $events = [[0.5, 'o', '$ '], [2.0, 'o', 'ls'], [1.0, 'o', "\r\n"]];

    yield 'asciicast v2' => [
      '{"version":2,"width":80,"height":24}' . "\n" . '[0.5,"o","$ ls\r\n"]' . "\n",
      'ls',
      'lines',
      \RuntimeException::class,
      'The recording is not in asciicast v3 format.',
    ];
    yield 'header without terminal size' => [
      '{"version":3}' . "\n" . '[0.5,"o","$ ls\r\n"]' . "\n",
      'ls',
      'lines',
      \RuntimeException::class,
      'The recording is not in asciicast v3 format.',
    ];
    yield 'empty recording' => [
      '',
      'ls',
      'lines',
      \RuntimeException::class,
      'The recording is not in asciicast v3 format.',
    ];
    yield 'no prompt' => [
      self::cast([[0.5, 'o', 'ls'], [1.0, 'o', "\r\n"]]),
      'ls',
      'lines',
      \RuntimeException::class,
      "The recording does not open with the prompt and the typed command 'ls'.",
    ];
    yield 'another command typed' => [
      self::cast($events),
      'pwd',
      'lines',
      \RuntimeException::class,
      "The recording does not open with the prompt and the typed command 'pwd'.",
    ];
    yield 'unknown frame mode' => [
      self::cast($events),
      'ls',
      'words',
      \InvalidArgumentException::class,
      "Unknown frame mode 'words'.",
    ];
  }

  public function testReadCast(): void {
    $content = implode("\n", [
      '{"version":3,"term":{"cols":80,"rows":24}}',
      '[0.5, "o", "ab"]',
      'not json',
      '[0.25, "i", "x"]',
      '[0.25, "o", "cd"]',
      '[0.5, "o"]',
      '["late", "o", "ef"]',
      '[1.0, "x", "0"]',
      '',
    ]);

    $this->assertSame([
      'header' => ['version' => 3, 'term' => ['cols' => 80, 'rows' => 24]],
      'stream' => 'abcd',
      'arrivals' => [0 => 0.5, 2 => 1.0],
    ], read_cast($content));
  }

  public function testReadCastWithoutHeader(): void {
    $this->assertSame(['header' => [], 'stream' => '', 'arrivals' => []], read_cast(''));
  }

  /**
   * @param array<int, float> $arrivals
   *   Arrival times keyed by stream offset.
   */
  #[DataProvider('dataProviderArrivalAt')]
  public function testArrivalAt(array $arrivals, int $offset, float $expected): void {
    $this->assertSame($expected, arrival_at($arrivals, $offset));
  }

  public static function dataProviderArrivalAt(): \Iterator {
    $arrivals = [0 => 0.5, 4 => 1.5, 10 => 3.0];

    yield 'no arrivals' => [[], 5, 0.0];
    yield 'start of the first chunk' => [$arrivals, 0, 0.5];
    yield 'inside the first chunk' => [$arrivals, 3, 0.5];
    yield 'start of a later chunk' => [$arrivals, 4, 1.5];
    yield 'inside a later chunk' => [$arrivals, 9, 1.5];
    yield 'past the last chunk' => [$arrivals, 50, 3.0];
  }

  /**
   * @param list<string> $expected
   *   The expected frames.
   */
  #[DataProvider('dataProviderSplitLines')]
  public function testSplitLines(string $data, array $expected): void {
    $this->assertSame($expected, split_lines($data));
  }

  public static function dataProviderSplitLines(): \Iterator {
    yield 'empty' => ['', []];
    yield 'no line break' => ['$ ', ['$ ']];
    yield 'line breaks kept' => ["a\r\nb\n", ["a\r\n", "b\n"]];
    yield 'text after the last line break' => ["a\nb", ["a\n", 'b']];
    yield 'blank lines' => ["\n\n", ["\n", "\n"]];
    yield 'redraw within a line' => ["0/10\e[1G\e[2K10/10\n", ["0/10\e[1G\e[2K10/10\n"]];
  }

  /**
   * @param list<string> $expected
   *   The expected frames.
   */
  #[DataProvider('dataProviderSplitRedraws')]
  public function testSplitRedraws(string $data, array $expected): void {
    $this->assertSame($expected, split_redraws($data));
  }

  public static function dataProviderSplitRedraws(): \Iterator {
    yield 'empty' => ['', []];
    yield 'no redraw' => ["a\nb\n", ["a\nb\n"]];
    yield 'redraws' => ["a\n\e[1Ab\n\e[12Ac\n", ["a\n", "\e[1Ab\n", "\e[12Ac\n"]];
    yield 'starts with a redraw' => ["\e[2Aa\n", ["\e[2Aa\n"]];
    yield 'other sequences' => ["\e[1G\e[2Ka\e[?25h\n", ["\e[1G\e[2Ka\e[?25h\n"]];
  }

  /**
   * @param array<string, string> $replacements
   *   Literal replacements keyed by the text to replace.
   */
  #[DataProvider('dataProviderSanitizeOutput')]
  public function testSanitizeOutput(string $text, array $replacements, string $expected): void {
    $this->assertSame($expected, sanitize_output($text, $replacements));
  }

  public static function dataProviderSanitizeOutput(): \Iterator {
    $paths = [
      '/var/folders/ws' => '/home/user/project',
      '/private/var/folders/ws' => '/home/user/project',
      '/Users/someone' => '/home/user',
    ];

    yield 'workspace path' => ['Directory : /var/folders/ws/build/web', $paths, 'Directory : /home/user/project/build/web'];
    yield 'resolved workspace path' => ['Note: Using configuration file /private/var/folders/ws/build/phpstan.neon.', $paths, 'Note: Using configuration file /home/user/project/build/phpstan.neon.'];
    yield 'home path' => ['Cache: /Users/someone/.cache', $paths, 'Cache: /home/user/.cache'];
    yield 'PHPUnit time' => ['Time: 00:15.641, Memory: 22.00 MB', [], 'Time: 00:14.625, Memory: 22.00 MB'];
    yield 'PHPCS time' => ['Time: 346ms; Memory: 18MB', [], 'Time: 230ms; Memory: 18MB'];
    yield 'coverage report time' => ['Generating code coverage report in HTML format ... done [00:00.020]', [], 'Generating code coverage report in HTML format ... done [00:00.010]'];
    yield 'Jest test duration' => ['✓ should format a date into a load time string (21 ms)', [], '✓ should format a date into a load time string (10 ms)'];
    yield 'Jest run time' => ["\e[1mTime:\e[22m        0.782 s", [], "\e[1mTime:\e[22m        0.705 s"];
    yield 'Jest run time with an estimate' => ['Time:        1.2 s, estimated 2 s', [], 'Time:        0.705 s'];
    yield 'browser output' => [
      'http://localhost:8000/sites/simpletest/browser_output/Drupal_Tests_your_extension_Functional_YourExtensionFunctionalTest-12-78200526.html',
      [],
      'http://localhost:8000/sites/simpletest/browser_output/Drupal_Tests_your_extension_Functional_YourExtensionFunctionalTest-12-58204617.html',
    ];
    yield 'one-time login link' => [
      'One-time login link: http://0.0.0.0:8000/user/reset/1/1791274419/FHd4oUDA15X1bmUc2BlGjFC3a4fCmclR0nusHML4Bwo/login',
      [],
      'One-time login link: http://0.0.0.0:8000/user/reset/1/1790000000/Aq3VnR8sKe1LwZp6Hc0YtJ5uMg9Xb2DfNo7Ti4WkQrE/login',
    ];
    yield 'webserver port on all interfaces' => ['URL       : http://0.0.0.0:8013', [], 'URL       : http://0.0.0.0:8000'];
    yield 'webserver port on localhost' => ['http://localhost:8099/sites/simpletest', [], 'http://localhost:8000/sites/simpletest'];
    yield 'webserver port on the loopback address' => ['http://127.0.0.1:8042/', [], 'http://127.0.0.1:8000/'];
    yield 'port outside the webserver range' => ['http://localhost:4444/wd/hub', [], 'http://localhost:4444/wd/hub'];
    yield 'versions' => ['PHPUnit 11.5.57 by Sebastian Bergmann and contributors.', [], 'PHPUnit 11.5.57 by Sebastian Bergmann and contributors.'];
    yield 'memory' => ['Memory: 22.00 MB', [], 'Memory: 22.00 MB'];
  }

  /**
   * @param array<string, string> $expected
   *   The expected replacements, keyed by the text to replace.
   */
  #[DataProvider('dataProviderPathReplacements')]
  public function testPathReplacements(string|false $home, array $expected): void {
    $workspace_dir = self::$sut . '/workspace';
    mkdir($workspace_dir);
    putenv($home === FALSE ? 'HOME' : 'HOME=' . $home);

    $replacements = path_replacements($workspace_dir);

    $this->assertSame('/home/user/project', $replacements[$workspace_dir] ?? NULL);
    $this->assertSame('/home/user/project', $replacements[(string) realpath($workspace_dir)] ?? NULL);

    foreach ($expected as $search => $replace) {
      $this->assertSame($replace, $replacements[$search] ?? NULL);
    }

    $this->assertCount(count(array_unique([$workspace_dir, (string) realpath($workspace_dir)])) + count($expected), $replacements);
  }

  public static function dataProviderPathReplacements(): \Iterator {
    yield 'home' => ['/Users/someone', ['/Users/someone' => '/home/user']];
    yield 'home is the root' => ['/', []];
    yield 'home is empty' => ['', []];
    yield 'home is not set' => [FALSE, []];
  }

  /**
   * @param list<string> $only
   *   The requested asset names.
   * @param list<string> $run
   *   The expected jobs to run.
   * @param list<string> $render
   *   The expected jobs to render.
   */
  #[DataProvider('dataProviderResolveJobs')]
  public function testResolveJobs(array $only, array $run, array $render): void {
    $this->assertSame(['run' => $run, 'render' => $render], resolve_jobs(['init', 'build', 'lint', 'test'], $only));
  }

  public static function dataProviderResolveJobs(): \Iterator {
    $all = ['init', 'build', 'lint', 'test'];

    yield 'all' => [[], $all, $all];
    yield 'first' => [['init'], ['init'], ['init']];
    yield 'second' => [['build'], ['init', 'build'], ['build']];
    yield 'third' => [['lint'], ['init', 'build', 'lint'], ['lint']];
    yield 'last' => [['test'], $all, ['test']];
    yield 'several, out of order' => [['test', 'init'], $all, ['init', 'test']];
    yield 'repeated' => [['lint', 'lint'], ['init', 'build', 'lint'], ['lint']];
  }

  /**
   * @param list<string> $only
   *   The requested asset names.
   */
  #[DataProvider('dataProviderResolveJobsRejectsUnknownName')]
  public function testResolveJobsRejectsUnknownName(array $only, string $message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($message);

    resolve_jobs(['init', 'build', 'lint', 'test'], $only);
  }

  public static function dataProviderResolveJobsRejectsUnknownName(): \Iterator {
    yield 'unknown' => [['docs'], 'Unknown asset(s): docs'];
    yield 'option' => [['--record', 'init'], 'Unknown asset(s): --record'];
    yield 'several unknown' => [['init', 'docs', 'logo'], 'Unknown asset(s): docs, logo'];
  }

  public function testJobsRunInWorkspaceOrder(): void {
    $this->assertSame(['init', 'build', 'lint', 'test'], array_keys(get_jobs()));
  }

  /**
   * @param list<string> $present
   *   Lines the expect script must contain.
   * @param list<string> $absent
   *   Lines the expect script must not contain.
   */
  #[DataProvider('dataProviderCreateExpectScript')]
  public function testCreateExpectScript(string $name, array $present, array $absent): void {
    $path = self::$sut . '/' . $name . '.exp';
    $workspace_dir = self::$sut . '/work space';

    create_expect_script($path, $workspace_dir, get_jobs()[$name]);

    $this->assertFileExists($path);
    $this->assertTrue(is_executable($path));

    $content = (string) file_get_contents($path);

    $common = [
      '#!/usr/bin/env expect',
      'log_user 1',
      'cd {' . $workspace_dir . '}',
      'set env(PS1) {$ }',
      'set env(TERM) xterm-256color',
      'set env(BASH_SILENCE_DEPRECATION_WARNING) 1',
      'spawn -noecho bash --norc --noprofile',
      'fconfigure $spawn_id -encoding binary',
      'fconfigure $user_spawn_id -encoding binary',
      'expect "\$ "',
      'send "\r"',
      'log_user 0',
      'send "exit\r"',
      'exit [lindex [wait] 3]',
    ];

    foreach (array_merge($common, $present) as $line) {
      $this->assertStringContainsString($line . "\n", $content);
    }

    foreach (array_merge(['send -h', 'send_human'], $absent) as $line) {
      $this->assertStringNotContainsString($line, $content);
    }
  }

  public static function dataProviderCreateExpectScript(): \Iterator {
    yield 'init' => [
      'init',
      ['set timeout 60', 'type_text {php init.php}', 'expect "Extension name"', 'type_text "Your Extension"', 'expect "Proceed"', 'press "y"'],
      ['set env(CI)', 'set env(WEBSERVER_HOST)'],
    ];
    yield 'build' => [
      'build',
      ['set timeout 600', 'type_text {ahoy build}', 'set env(WEBSERVER_HOST) {0.0.0.0}'],
      ['set env(CI)', 'expect "Extension name"'],
    ];
    yield 'lint' => [
      'lint',
      ['set timeout 600', 'type_text {ahoy lint}', 'set env(CI) {true}', 'set env(FORCE_COLOR) {1}'],
      ['set env(WEBSERVER_HOST)', 'expect "Extension name"'],
    ];
    yield 'test' => [
      'test',
      ['set timeout 600', 'type_text {ahoy test}', 'set env(CI) {true}', 'set env(FORCE_COLOR) {1}'],
      ['set env(WEBSERVER_HOST)', 'expect "Extension name"'],
    ];
  }

  public function testInitStepsAnswerEveryInitPrompt(): void {
    $init = (string) file_get_contents(dirname(__DIR__, 4) . '/init.php');
    preg_match_all("/Prompty::(?:text|select|multiselect|confirm)\(\s*'([^']+)'/", $init, $labels);
    preg_match_all('/^expect "([^"]+)"$/m', init_steps(), $patterns);

    $this->assertNotEmpty($labels[1]);
    $this->assertCount(count($labels[1]), $patterns[1], 'Every init prompt has 1 expect statement, in order.');

    foreach ($labels[1] as $index => $label) {
      $this->assertStringStartsWith($patterns[1][$index], $label);
    }
  }

  public function testSessionTail(): void {
    $lines = [];

    for ($i = 1; $i <= 12; $i++) {
      $lines[] = sprintf("\e[32mline %d\e[0m\r\n", $i);
    }

    $events = [
      [0.1, 'o', "\e]0;title\x07" . implode('', array_slice($lines, 0, 6))],
      [0.1, 'o', implode('', array_slice($lines, 6)) . "\r\n\rlast\e[K"],
    ];

    $this->assertSame(implode(PHP_EOL, ['line 11', 'line 12', 'last']), session_tail(self::cast($events), 3));
    $this->assertSame('', session_tail(''));
  }

  /**
   * @param list<string> $lines
   *   The lines to join.
   */
  #[DataProvider('dataProviderJoinLines')]
  public function testJoinLines(array $lines, string $expected): void {
    $this->assertSame($expected, join_lines(...$lines));
  }

  public static function dataProviderJoinLines(): \Iterator {
    yield 'none' => [[], ''];
    yield 'one' => [['a'], 'a'];
    yield 'several' => [['a', 'b'], 'a' . PHP_EOL . 'b'];
    yield 'empty lines skipped' => [['', 'a', '', 'b', ''], 'a' . PHP_EOL . 'b'];
    yield 'only empty lines' => [['', ''], ''];
    yield 'zero kept' => [['0', 'a'], '0' . PHP_EOL . 'a'];
  }

  public function testRemoveDirRemovesReadOnlyDirectories(): void {
    $directory = self::$sut . '/workspace';
    mkdir($directory . '/build/web/sites/default', 0755, TRUE);
    file_put_contents($directory . '/build/web/sites/default/settings.php', '<?php');
    chmod($directory . '/build/web/sites/default', 0555);

    remove_dir($directory);

    $this->assertDirectoryDoesNotExist($directory);
  }

  public function testRemoveDirIgnoresMissingDirectory(): void {
    $directory = self::$sut . '/missing';

    remove_dir($directory);

    $this->assertDirectoryDoesNotExist($directory);
  }

  /**
   * Build a recording from its events.
   *
   * @param list<array{0: float|int, 1: string, 2: string}> $events
   *   Events with relative timestamps.
   */
  protected static function cast(array $events): string {
    $lines = [json_encode(['version' => 3, 'term' => ['cols' => 80, 'rows' => 24], 'timestamp' => 1791274314], JSON_THROW_ON_ERROR)];

    foreach ($events as $event) {
      $lines[] = json_encode($event, JSON_THROW_ON_ERROR);
    }

    return implode("\n", $lines) . "\n";
  }

  /**
   * Build a canonical recording from its events.
   *
   * @param array<array{0: float, 1: string, 2: string}> $events
   *   Events with relative timestamps.
   */
  protected static function canonical(array $events): string {
    $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $lines = [json_encode(['version' => 3, 'term' => ['cols' => 80, 'rows' => 24]], $flags)];

    foreach ($events as $event) {
      $lines[] = json_encode($event, $flags);
    }

    return implode("\n", $lines) . "\n";
  }

}
