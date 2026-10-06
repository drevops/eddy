#!/usr/bin/env php
<?php

/**
 * @file
 * Generate animated SVG assets from asciinema recordings.
 *
 * Records the init, build, lint and test sessions in a clean workspace and
 * renders each recording as an animated SVG for README.md.
 *
 * A recording is rewritten onto a canonical timeline before it is rendered,
 * so recording the same session twice produces the same SVG. Frames are cut
 * where the session's output defines them, every gap becomes 1 of 2 fixed
 * durations, and values that change on every run are masked.
 *
 * Dependencies: asciinema 3, expect, node, npm
 *
 * Environment variables:
 * - SCRIPT_QUIET: Set to '1' to suppress verbose messages.
 * - SCRIPT_KEEP_CASTS: Set to '1' to keep the recordings for inspection.
 * - SCRIPT_RUN_SKIP: Set to '1' to skip running of the script. Useful when
 *   unit-testing or requiring this file from other files.
 *
 * Usage:
 * @code
 * php .eddy/assets/update-assets.php
 * php .eddy/assets/update-assets.php lint
 * @endcode
 *
 * Passing 1 or more asset names (init, build, lint, test) renders only those
 * assets. Every recording up to the last named one still runs, because each
 * recording prepares the workspace for the next.
 */

declare(strict_types=1);

namespace DrevOps\Eddy\Assets;

/**
 * Terminal width (columns).
 */
const TERMINAL_COLS = 80;

/**
 * Terminal height (rows).
 */
const TERMINAL_ROWS = 24;

/**
 * Delay between typed characters (seconds).
 *
 * Each keystroke is drawn before the next one arrives, so a widget redraws
 * once per character.
 */
const TYPE_DELAY = 0.1;

/**
 * Silence that ends a settle in the expect scripts (seconds).
 *
 * Every deliberate key waits for it, so the key's redraw arrives well after
 * the output before it.
 */
const SETTLE_TIME = 1;

/**
 * Gap below which a redraw continues the previous step (seconds).
 *
 * It sits well above the gaps within a burst of output and well below
 * SETTLE_TIME.
 */
const MERGE_WINDOW = 0.5;

/**
 * Rendered gap between frames within a step (seconds).
 *
 * Typing and command output play back at this speed.
 */
const FRAME_DELAY = 0.1;

/**
 * Rendered gap before each step (seconds).
 */
const STEP_DELAY = 1.0;

/**
 * Rendered pause on the last frame before the animation loops (seconds).
 */
const END_PAUSE = 3.0;

/**
 * Get all job definitions, in the order they run.
 *
 * Each recording runs in the workspace the previous ones left: init
 * initializes the extension and build assembles the codebase that lint and
 * test run against.
 *
 * - command: The command typed at the shell prompt.
 * - frames: 'redraws' to cut a frame where a widget redraws, or 'lines' to
 *   cut a frame after every line of output.
 * - env: Environment variables for the recorded shell.
 * - steps: Expect statements that answer the command's prompts.
 * - timeout: Seconds to wait for each prompt and for the command to end.
 * - prepare: Command run in the workspace before the recording.
 * - cleanup: Command run in the workspace after the last recording.
 *
 * @return array<string, array{command: string, frames: string, env?: array<string, string>, steps?: string, timeout?: int, prepare?: string, cleanup?: string}>
 *   Job definitions keyed by asset name.
 */
function get_jobs(): array {
  return [
    'init' => [
      'command' => 'php init.php',
      'frames' => 'redraws',
      'steps' => init_steps(),
      'timeout' => 60,
    ],
    'build' => [
      'command' => 'ahoy build',
      'frames' => 'lines',
      'env' => ['WEBSERVER_HOST' => '0.0.0.0'],
      'cleanup' => 'ahoy stop',
    ],
    'lint' => [
      'command' => 'ahoy lint',
      'frames' => 'lines',
      // Without CI, tools draw progress and status lines, and how many they
      // draw depends on timing. CI also turns colors off, so FORCE_COLOR
      // turns them back on.
      'env' => ['CI' => 'true', 'FORCE_COLOR' => '1'],
      // 'ahoy lint' installs the CSpell dependencies when they are missing,
      // and npm's install output differs between runs.
      'prepare' => 'npm install --no-audit --no-fund',
    ],
    'test' => [
      'command' => 'ahoy test',
      'frames' => 'lines',
      'env' => ['CI' => 'true', 'FORCE_COLOR' => '1'],
      // 'ahoy test' runs the FunctionalJavascript suite but, unlike
      // 'ahoy test-functional-javascript', does not start the browser it
      // needs.
      'prepare' => 'ahoy browser-start',
      'cleanup' => 'ahoy browser-stop',
    ],
  ];
}

/**
 * Get the expect statements that answer the init prompts.
 *
 * @return string
 *   Expect statements, run after the command is typed.
 */
function init_steps(): string {
  return <<<'EXPECT'
# Text: Extension name - type "Your Extension".
expect "Extension name"
settle
type_text "Your Extension"
press "\r"

# Text: Machine name - keep the placeholder, so init.php derives the machine
# name from the extension name.
expect "Machine name"
press "\r"

# Select: Extension type - "Module" is pre-selected.
expect "Extension type"
press "\r"

# Multi-select: Target Drupal versions - keep the pre-checked Drupal 11.
expect "Target Drupal versions"
press "\r"

# Multi-select: Command wrapper - check the focused "Ahoy".
expect "Command wrapper"
press " "
press "\r"

# Multi-select: Tools - keep every pre-checked tool.
expect "Tools"
press "\r"

# Confirm: Keep Cloudflare tunnel support - accept the default "Yes".
expect "Keep Cloudflare tunnel support"
press "\r"

# Confirm: Keep example lifecycle scripts - accept the default "No".
expect "Keep example lifecycle scripts"
press "\r"

# Confirm: Remove this script - type "y".
expect "Remove this script"
press "y"
press "\r"

# Confirm: Proceed with project init - type "y".
expect "Proceed"
press "y"
press "\r"
EXPECT;
}

/**
 * Main functionality.
 *
 * @param array<string> $only
 *   Asset names to render (e.g. ['init']). When empty, every asset is
 *   rendered.
 */
function main(array $only = []): void {
  $assets_dir = __DIR__;
  $project_dir = dirname(__DIR__, 2);
  $recordings_dir = $project_dir . '/.artifacts/tmp/asciinema';

  info('Eddy - Asset Generator');
  info('======================');
  info('');

  $jobs = get_jobs();
  ['run' => $run, 'render' => $render] = resolve_jobs(array_keys($jobs), array_values($only));

  check_dependencies();
  install_node_dependencies($assets_dir);

  remove_dir($recordings_dir);
  mkdir($recordings_dir, 0755, TRUE);

  $workspace_dir = create_workspace($project_dir);

  info('Workspace: ' . $workspace_dir);
  info('');

  $cleanups = [];

  try {
    foreach ($run as $name) {
      $job = $jobs[$name];

      info('--- Recording: ' . $name . ' ---');

      if (isset($job['cleanup'])) {
        $cleanups[] = $job['cleanup'];
      }

      if (isset($job['prepare'])) {
        $result = run_in_workspace($workspace_dir, $job['prepare']);

        if ($result['exit_code'] !== 0) {
          throw new \RuntimeException(sprintf("Preparing '%s' failed:\n%s", $name, $result['output']));
        }
      }

      $expect_script = $recordings_dir . '/' . $name . '.exp';
      $cast_file = $recordings_dir . '/' . $name . '.cast';

      create_expect_script($expect_script, $workspace_dir, $job);

      try {
        record_session($expect_script, $cast_file);
      }
      catch (\RuntimeException $exception) {
        throw new \RuntimeException(sprintf("Recording '%s' failed. %s", $name, $exception->getMessage()), 0, $exception);
      }

      if (in_array($name, $render, TRUE)) {
        $canonical_file = $recordings_dir . '/' . $name . '.canonical.cast';
        $canonical = canonicalize_cast((string) file_get_contents($cast_file), $job['command'], $job['frames'], path_replacements($workspace_dir));
        file_put_contents($canonical_file, $canonical);
        convert_to_svg($canonical_file, $assets_dir . '/' . $name . '.svg', $assets_dir);
        info('  Rendered: ' . $name . '.svg');
      }

      info('  Done: ' . $name);
      info('');
    }
  }
  catch (\Exception $exception) {
    throw new \RuntimeException($exception->getMessage() . PHP_EOL . 'Recordings kept in ' . $recordings_dir, 0, $exception);
  }
  finally {
    // The webserver and the browser outlive their workspace unless they are
    // stopped before it is removed.
    foreach (array_reverse($cleanups) as $cleanup) {
      run_in_workspace($workspace_dir, $cleanup);
    }

    info('Cleaning up workspace: ' . $workspace_dir);
    remove_dir($workspace_dir);
  }

  if (getenv('SCRIPT_KEEP_CASTS') === '1') {
    info('Keeping recordings: ' . $recordings_dir);
  }
  else {
    remove_dir($recordings_dir);
  }

  info('');
  info('Done. SVG assets updated in ' . $assets_dir);
}

/**
 * Resolve which recordings run and which of them are rendered.
 *
 * Each recording prepares the workspace for the next, so every recording up
 * to the last requested one runs, and only the requested ones are rendered.
 *
 * @param list<string> $names
 *   All job names, in the order they run.
 * @param list<string> $only
 *   Requested job names. When empty, every job runs and is rendered.
 *
 * @return array{run: list<string>, render: list<string>}
 *   Job names to run and job names to render, in the order they run.
 *
 * @throws \RuntimeException
 *   When a requested name is not a job.
 */
function resolve_jobs(array $names, array $only): array {
  if ($only === []) {
    return ['run' => $names, 'render' => $names];
  }

  $unknown = array_diff($only, $names);

  if ($unknown !== []) {
    throw new \RuntimeException('Unknown asset(s): ' . implode(', ', $unknown));
  }

  $last = max(array_map(static fn(string $name): int => (int) array_search($name, $names, TRUE), $only));
  $run = array_slice($names, 0, $last + 1);

  return ['run' => $run, 'render' => array_values(array_intersect($run, $only))];
}

/**
 * Run a command in the workspace without recording it.
 *
 * @param string $workspace_dir
 *   Path to the workspace directory.
 * @param string $command
 *   The command to run.
 *
 * @return array{exit_code: int, output: string}
 *   The exit code and combined output.
 */
function run_in_workspace(string $workspace_dir, string $command): array {
  $cmd = sprintf('cd %s && %s 2>&1', escapeshellarg($workspace_dir), $command);

  $output = [];
  $exit_code = 0;
  exec($cmd, $output, $exit_code);

  return [
    'exit_code' => $exit_code,
    'output' => implode(PHP_EOL, $output),
  ];
}

/**
 * Check that all required dependencies are installed.
 */
function check_dependencies(): void {
  $deps = ['asciinema', 'expect', 'node', 'npm'];
  $missing = [];

  foreach ($deps as $dep) {
    if (empty(shell_exec('which ' . escapeshellarg($dep) . ' 2>/dev/null'))) {
      $missing[] = $dep;
    }
  }

  if (!empty($missing)) {
    throw new \RuntimeException('Missing required dependencies: ' . implode(', ', $missing));
  }

  info('All dependencies found.');
}

/**
 * Install Node.js dependencies for svg-term rendering.
 *
 * @param string $assets_dir
 *   Path to the assets directory containing svg-term-render.js.
 */
function install_node_dependencies(string $assets_dir): void {
  info('Installing svg-term Node.js dependency...');

  $node_modules = $assets_dir . '/node_modules';
  if (is_dir($node_modules . '/svg-term')) {
    info('svg-term already installed.');

    return;
  }

  $cmd = sprintf('npm install --prefix %s svg-term@1.3.1 2>&1', escapeshellarg($assets_dir));
  $output = shell_exec($cmd);
  if (!is_dir($node_modules . '/svg-term')) {
    throw new \RuntimeException('Failed to install svg-term: ' . (is_string($output) ? $output : 'unknown error'));
  }

  info('svg-term installed.');
}

/**
 * Create a temporary workspace by exporting the current git tree.
 *
 * @param string $project_dir
 *   Path to the project root.
 *
 * @return string
 *   Path to the temporary workspace directory.
 */
function create_workspace(string $project_dir): string {
  $workspace_dir = sys_get_temp_dir() . '/eddy-assets-' . bin2hex(random_bytes(6));
  mkdir($workspace_dir, 0755, TRUE);

  info('Exporting current branch to workspace...');

  $cmd = sprintf(
    'cd %s && git archive HEAD | tar -x -C %s 2>&1',
    escapeshellarg($project_dir),
    escapeshellarg($workspace_dir)
  );

  $output = shell_exec($cmd);
  if (!file_exists($workspace_dir . '/init.php')) {
    throw new \RuntimeException('Failed to export git archive: ' . (is_string($output) ? $output : 'unknown error'));
  }

  return $workspace_dir;
}

/**
 * Create the expect script that drives a recording.
 *
 * The script types the job's command at a shell prompt, answers its prompts
 * and exits the shell with the command's exit code. The exit is not
 * recorded, so the recording ends on the prompt the command returns to.
 *
 * @param string $path
 *   Path to write the expect script to.
 * @param string $workspace_dir
 *   Path to the workspace the command runs in.
 * @param array{command: string, env?: array<string, string>, steps?: string, timeout?: int} $job
 *   The job definition.
 */
function create_expect_script(string $path, string $workspace_dir, array $job): void {
  $env = '';
  foreach ($job['env'] ?? [] as $name => $value) {
    $env .= sprintf('set env(%s) {%s}', $name, $value) . "\n";
  }

  $command = $job['command'];
  $steps = $job['steps'] ?? '';
  $timeout = $job['timeout'] ?? 600;
  $settle_time = SETTLE_TIME;
  $type_delay = TYPE_DELAY;

  $content = <<<EXPECT
#!/usr/bin/env expect

set timeout {$timeout}
log_user 1

expect_after {
    timeout { puts stderr "Timed out waiting for the session."; exit 1 }
    eof { puts stderr "The session ended unexpectedly."; exit 1 }
}

# Expect copies the session's output only while it waits in 'expect', so
# this records everything pending before the next key is sent.
proc settle {} {
    set timeout {$settle_time}
    expect {
        -re {.+} { exp_continue }
        timeout {}
    }
}

proc type_text {text} {
    foreach char [split \$text ""] {
        send -- \$char
        sleep {$type_delay}
    }
}

proc press {key} {
    settle
    send -- \$key
}

cd {{$workspace_dir}}

{$env}
# The prompt, the colors tools pick from TERM and the macOS bash banner
# would otherwise depend on the environment this script runs in.
set env(PS1) {\$ }
set env(TERM) xterm-256color
set env(BASH_SILENCE_DEPRECATION_WARNING) 1
spawn -noecho bash --norc --noprofile

# Tcl's UTF-8 decoding mangles characters beyond U+FFFF, such as emoji, so
# the session's bytes pass through undecoded.
fconfigure \$spawn_id -encoding binary
fconfigure \$user_spawn_id -encoding binary

expect "\\$ "
settle
type_text {{$command}}
settle
send "\\r"

{$steps}

expect "\\$ "
log_user 0
send "exit\\r"
expect eof
exit [lindex [wait] 3]

EXPECT;

  file_put_contents($path, $content);
  chmod($path, 0700);
}

/**
 * Record a session with asciinema.
 *
 * @param string $expect_script
 *   Path to the expect script that drives the session.
 * @param string $cast_file
 *   Path to write the recording to.
 *
 * @throws \RuntimeException
 *   When the session fails or leaves no recording.
 */
function record_session(string $expect_script, string $cast_file): void {
  if (!is_file($expect_script)) {
    throw new \RuntimeException('Missing expect script: ' . $expect_script);
  }

  // Without '--return', asciinema exits with 0 whatever the session does.
  // '--headless' keeps it off the terminal this script runs in.
  $cmd = sprintf(
    'asciinema rec --headless --quiet --return --command=%s --window-size=%dx%d --overwrite %s 2>&1',
    escapeshellarg($expect_script),
    TERMINAL_COLS,
    TERMINAL_ROWS,
    escapeshellarg($cast_file)
  );

  $output = [];
  $exit_code = 0;
  exec($cmd, $output, $exit_code);

  if (!is_file($cast_file)) {
    throw new \RuntimeException(join_lines(sprintf('No recording was written to %s.', $cast_file), ...$output));
  }

  if ($exit_code !== 0) {
    $tail = session_tail((string) file_get_contents($cast_file));
    throw new \RuntimeException(join_lines(sprintf('The session exited with code %d. Its last output:', $exit_code), $tail, ...$output));
  }
}

/**
 * Read a recording into a single output stream.
 *
 * @param string $content
 *   The recording, in asciicast v3 format.
 *
 * @return array{header: array<mixed>, stream: string, arrivals: array<int, float>}
 *   The decoded header, the joined output, and the time each output event
 *   arrived, keyed by the stream offset it starts at.
 */
function read_cast(string $content): array {
  $lines = explode("\n", $content);
  $header = json_decode(array_shift($lines), TRUE);
  $stream = '';
  $arrivals = [];
  $time = 0.0;

  foreach ($lines as $line) {
    $event = json_decode(trim($line), TRUE);

    if (!is_array($event)) {
      continue;
    }

    if (!isset($event[0], $event[1], $event[2])) {
      continue;
    }

    if (!is_numeric($event[0])) {
      continue;
    }

    if (!is_string($event[2])) {
      continue;
    }

    // Timestamps are relative, so an event that draws nothing still moves
    // the clock.
    $time += (float) $event[0];

    if ($event[1] !== 'o') {
      continue;
    }

    $arrivals[strlen($stream)] = $time;
    $stream .= $event[2];
  }

  return [
    'header' => is_array($header) ? $header : [],
    'stream' => $stream,
    'arrivals' => $arrivals,
  ];
}

/**
 * Rewrite a recording onto a canonical timeline.
 *
 * A recording carries whatever chunks the terminal delivered, at whatever
 * moment the scheduler delivered them, so 2 recordings of one session differ
 * in their frames and durations. The output is joined into 1 stream, cut
 * into frames where the session's own output defines them, and every frame
 * gets 1 of 2 fixed delays.
 *
 * The stream opens with the shell prompt and the typed command; each typed
 * character becomes a frame. The rest is cut according to $frames:
 * - 'redraws': before each cursor-up sequence, which starts every widget
 *   redraw. A frame that arrived within MERGE_WINDOW of the previous one
 *   continues its step; a later one starts a new step.
 * - 'lines': after each line break. Every line continues the step that the
 *   command started.
 *
 * @param string $content
 *   The recording, in asciicast v3 format.
 * @param string $command
 *   The command typed at the prompt.
 * @param string $frames
 *   How to cut the output into frames: 'redraws' or 'lines'.
 * @param array<string, string> $replacements
 *   Literal replacements for the output, keyed by the text to replace.
 *
 * @return string
 *   The canonical recording, in asciicast v3 format.
 *
 * @throws \RuntimeException
 *   When the recording is not in asciicast v3 format, or does not open with
 *   the prompt and the typed command.
 */
function canonicalize_cast(string $content, string $command, string $frames, array $replacements = []): string {
  ['header' => $header, 'stream' => $stream, 'arrivals' => $arrivals] = read_cast($content);

  $term = $header['term'] ?? NULL;
  if (($header['version'] ?? NULL) !== 3 || !is_array($term) || !is_int($term['cols'] ?? NULL) || !is_int($term['rows'] ?? NULL)) {
    throw new \RuntimeException('The recording is not in asciicast v3 format.');
  }

  $prompt = strpos($stream, '$ ');
  if ($prompt === FALSE || substr($stream, $prompt + 2, strlen($command)) !== $command) {
    throw new \RuntimeException(sprintf("The recording does not open with the prompt and the typed command '%s'.", $command));
  }

  $offset = $prompt + 2;
  $canonical = [['text' => substr($stream, 0, $offset), 'delay' => 0.0]];

  foreach (mb_str_split($command) as $index => $char) {
    $canonical[] = ['text' => $char, 'delay' => $index === 0 ? STEP_DELAY : FRAME_DELAY];
    $offset += strlen($char);
  }

  $output = substr($stream, $offset);
  $parts = match ($frames) {
    'redraws' => split_redraws($output),
    'lines' => split_lines($output),
    default => throw new \InvalidArgumentException(sprintf("Unknown frame mode '%s'.", $frames)),
  };

  $previous = 0.0;

  foreach ($parts as $index => $part) {
    $arrival = arrival_at($arrivals, $offset);

    if ($index === 0) {
      $delay = STEP_DELAY;
    }
    elseif ($frames === 'lines') {
      $delay = FRAME_DELAY;
    }
    else {
      $delay = $arrival - $previous < MERGE_WINDOW ? FRAME_DELAY : STEP_DELAY;
    }

    $canonical[] = ['text' => $part, 'delay' => $delay];
    $previous = $arrival;
    $offset += strlen($part);
  }

  $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
  $lines = [json_encode(['version' => 3, 'term' => ['cols' => $term['cols'], 'rows' => $term['rows']]], $flags)];

  foreach ($canonical as $frame) {
    $lines[] = json_encode([$frame['delay'], 'o', sanitize_output($frame['text'], $replacements)], $flags);
  }

  $lines[] = json_encode([END_PAUSE, 'o', ' '], $flags);

  return implode("\n", $lines) . "\n";
}

/**
 * Return the time the chunk holding an offset arrived.
 *
 * @param array<int, float> $arrivals
 *   Times the recording captured, keyed by the offset each chunk starts at.
 * @param int $offset
 *   Offset into the stream.
 *
 * @return float
 *   Seconds from the start of the recording.
 */
function arrival_at(array $arrivals, int $offset): float {
  $time = 0.0;

  foreach ($arrivals as $start => $at) {
    if ($start > $offset) {
      break;
    }

    $time = $at;
  }

  return $time;
}

/**
 * Split output into the redraws it is made of.
 *
 * A widget starts every redraw by moving the cursor up, so that sequence
 * marks where one frame ends and the next begins.
 *
 * @param string $data
 *   The output.
 *
 * @return list<string>
 *   1 entry per redraw, in order.
 */
function split_redraws(string $data): array {
  $parts = preg_split('/(?=\x1b\[\d+A)/', $data, -1, PREG_SPLIT_NO_EMPTY);

  return $parts === FALSE ? [$data] : $parts;
}

/**
 * Split output into lines, keeping each line break.
 *
 * Progress bars and spinners redraw within a line, so only the state a line
 * ends in is drawn.
 *
 * @param string $data
 *   The output.
 *
 * @return list<string>
 *   1 entry per line, in order. Text after the last line break is the last
 *   entry.
 */
function split_lines(string $data): array {
  $parts = preg_split('/(?<=\n)/', $data, -1, PREG_SPLIT_NO_EMPTY);

  return $parts === FALSE ? [$data] : $parts;
}

/**
 * Replace the values in recorded output that differ between runs.
 *
 * @param string $text
 *   The output.
 * @param array<string, string> $replacements
 *   Literal replacements, keyed by the text to replace.
 *
 * @return string
 *   The output with fixed values.
 */
function sanitize_output(string $text, array $replacements): string {
  $text = strtr($text, $replacements);

  foreach (volatile_patterns() as $pattern => $replacement) {
    $text = (string) preg_replace($pattern, $replacement, $text);
  }

  return $text;
}

/**
 * Get the literal paths to replace in recorded output.
 *
 * @param string $workspace_dir
 *   Path to the workspace directory.
 *
 * @return array<string, string>
 *   Replacements keyed by the path to replace.
 */
function path_replacements(string $workspace_dir): array {
  $replacements = [$workspace_dir => '/home/user/project'];

  // Tools print the resolved path, which on macOS gains a '/private' prefix.
  $real_workspace_dir = realpath($workspace_dir);
  if ($real_workspace_dir !== FALSE) {
    $replacements[$real_workspace_dir] = '/home/user/project';
  }

  $home = getenv('HOME');
  if (is_string($home) && strlen($home) > 1) {
    $replacements[$home] = '/home/user';
  }

  return $replacements;
}

/**
 * Get the patterns for values that change on every run.
 *
 * Each value is replaced with a fixed one, so a regeneration differs only
 * where the output does.
 *
 * @return array<string, string>
 *   Replacements keyed by regular expression.
 */
function volatile_patterns(): array {
  return [
    // PHPUnit: 'Time: 00:14.625, Memory: 22.00 MB'.
    '/Time: \d{2}:\d{2}\.\d{3}, Memory:/' => 'Time: 00:14.625, Memory:',
    // PHPCS: 'Time: 230ms; Memory: 18MB'.
    '/Time: \d+ms; Memory:/' => 'Time: 230ms; Memory:',
    // PHPUnit coverage report: 'done [00:00.002]'.
    '/done \[\d{2}:\d{2}\.\d{3}\]/' => 'done [00:00.010]',
    // Jest test duration: '(21 ms)'.
    '/\(\d+ ms\)/' => '(10 ms)',
    // Jest run time: 'Time:        0.705 s, estimated 1 s'.
    '/(Time:(?:\x1b\[[0-9;]*m)?\s+)\d+(?:\.\d+)? s(?:, estimated \d+ s)?/' => '${1}0.705 s',
    // Drupal browser output file: '...FunctionalTest-1-78200526.html'.
    '/(Test-\d+-)\d+(\.html)/' => '${1}58204617${2}',
    // One-time login link: '/user/reset/1/<timestamp>/<hash>/login'.
    '#(/user/reset/\d+/)\d+/[\w-]+(/login)#' => '${1}1790000000/Aq3VnR8sKe1LwZp6Hc0YtJ5uMg9Xb2DfNo7Ti4WkQrE${2}',
    // Webserver port, discovered from the first free port in 8000-8099.
    '#(https?://(?:localhost|0\.0\.0\.0|127\.0\.0\.1)):80\d\d\b#' => '${1}:8000',
  ];
}

/**
 * Get the last lines a recorded session printed.
 *
 * @param string $content
 *   The recording, in asciicast v3 format.
 * @param int $count
 *   The number of lines to return.
 *
 * @return string
 *   The lines, without terminal escape sequences.
 */
function session_tail(string $content, int $count = 10): string {
  $text = (string) preg_replace('/\x1b(?:\[[0-9;?]*[ -\/]*[@-~]|\][^\x07]*\x07)/', '', read_cast($content)['stream']);

  $lines = array_filter(array_map(trim(...), preg_split('/\r?\n|\r/', $text) ?: []), static fn(string $line): bool => $line !== '');

  return implode(PHP_EOL, array_slice($lines, -$count));
}

/**
 * Join message lines, skipping empty ones.
 *
 * @param string ...$lines
 *   The lines to join.
 *
 * @return string
 *   The lines, separated by line breaks.
 */
function join_lines(string ...$lines): string {
  return implode(PHP_EOL, array_filter($lines, static fn(string $line): bool => $line !== ''));
}

/**
 * Convert a cast file to an animated SVG.
 *
 * @param string $cast_file
 *   Path to the input cast file.
 * @param string $svg_file
 *   Path to the output SVG file.
 * @param string $assets_dir
 *   Path to the assets directory containing svg-term-render.js.
 */
function convert_to_svg(string $cast_file, string $svg_file, string $assets_dir): void {
  $cmd = sprintf(
    'node %s %s %s --line-height 1.1 2>&1',
    escapeshellarg($assets_dir . '/svg-term-render.js'),
    escapeshellarg($cast_file),
    escapeshellarg($svg_file)
  );

  $output = [];
  $exit_code = 0;
  exec($cmd, $output, $exit_code);

  if ($exit_code !== 0) {
    throw new \RuntimeException('Failed to convert cast to SVG: ' . $cast_file . PHP_EOL . implode(PHP_EOL, $output));
  }
}

/**
 * Remove a directory recursively.
 *
 * @param string $directory
 *   Path to the directory to remove.
 */
function remove_dir(string $directory): void {
  if (!is_dir($directory)) {
    return;
  }

  // Drupal's installer makes 'sites/default' read-only, and a read-only
  // directory keeps its files.
  exec(sprintf('chmod -R u+w %s 2>&1', escapeshellarg($directory)));
  exec(sprintf('rm -rf %s 2>&1', escapeshellarg($directory)));
}

/**
 * Print an informational message.
 *
 * @param string $message
 *   The message to print.
 */
function info(string $message): void {
  if (getenv('SCRIPT_QUIET') === '1') {
    return;
  }
  print $message . PHP_EOL;
}

// Entrypoint.
//
// @codeCoverageIgnoreStart
if (getenv('SCRIPT_RUN_SKIP') != 1) {
  ini_set('display_errors', '1');

  if (PHP_SAPI !== 'cli' || !empty($_SERVER['REMOTE_ADDR'])) {
    die('This script can be only ran from the command line.');
  }

  set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
      return FALSE;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
  });

  try {
    $arguments = is_array($_SERVER['argv'] ?? NULL) ? array_values(array_filter($_SERVER['argv'], is_string(...))) : [];
    main(array_slice($arguments, 1));
  }
  catch (\Exception $exception) {
    fwrite(STDERR, PHP_EOL . 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
  }
}
// @codeCoverageIgnoreEnd
