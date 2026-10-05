#!/usr/bin/env php
<?php

/**
 * @file
 * Generate animated SVG assets from asciinema recordings.
 *
 * Records terminal sessions for init, build, lint, and test commands,
 * then converts the recordings to animated SVGs for use in README.md.
 *
 * Supports parallel execution: the lint and test recordings are launched as
 * parallel worker processes for faster generation.
 *
 * Init and build run sequentially (init initialises the workspace, build
 * assembles the Drupal codebase), then lint and test run in parallel on
 * the assembled workspace.
 *
 * Dependencies: asciinema, expect, node, npm
 *
 * Environment variables:
 * - SCRIPT_QUIET: Set to '1' to suppress verbose messages.
 *
 * Usage:
 * @code
 * php .eddy/assets/update-assets.php
 * php .eddy/assets/update-assets.php init
 * php .eddy/assets/update-assets.php --record init --workspace /tmp/ws
 * @endcode
 *
 * Passing 1 or more asset names (init, build, lint, test) regenerates only
 * those assets; with none, every asset is regenerated.
 */

declare(strict_types=1);

define('TERMINAL_COLS', 80);
define('TERMINAL_ROWS', 24);

// Delay before interacting with prompts in expect scripts (seconds).
define('PROMPT_DELAY', 1);

// Maximum idle time in recordings (seconds).
define('MAX_IDLE_TIME', 3);

// Pause at the end of each recording before the animation loops (seconds).
define('END_PAUSE', 10);

/**
 * Get all job definitions.
 *
 * @param string $workspace_dir
 *   Path to the workspace directory.
 *
 * @return array<string, array<string, mixed>>
 *   Keyed by job name, each containing expect_fn and related config.
 */
function get_jobs(string $workspace_dir): array {
  return [
    'init' => [
      'expect_fn' => 'create_init_expect_script',
      'script' => $workspace_dir . '/init.php',
    ],
    'build' => [
      'expect_fn' => 'create_command_expect_script',
      'script' => $workspace_dir,
      'command' => 'ahoy build',
      'speed' => 2.0,
      'env' => ['WEBSERVER_HOST' => '0.0.0.0'],
    ],
    'lint' => [
      'expect_fn' => 'create_command_expect_script',
      'script' => $workspace_dir,
      'command' => 'ahoy lint',
    ],
    'test' => [
      'expect_fn' => 'create_command_expect_script',
      'script' => $workspace_dir,
      'command' => 'ahoy test',
    ],
  ];
}

/**
 * Main functionality - orchestrator mode.
 *
 * Runs init and build sequentially (they prepare the workspace), then
 * launches lint and test as parallel worker processes.
 *
 * @param array<string> $only
 *   Optional list of asset names to regenerate (e.g. ['init']). When empty,
 *   every asset is regenerated.
 */
function main(array $only = []): void {
  $script_dir = dirname(__FILE__);
  $project_dir = dirname($script_dir, 2);
  $assets_dir = $script_dir;

  info('Eddy - Asset Generator');
  info('======================');
  info('');

  check_dependencies();
  install_node_dependencies($assets_dir);

  $workspace_dir = create_workspace($project_dir);

  info('Workspace: ' . $workspace_dir);
  info('');

  $jobs = get_jobs($workspace_dir);

  if ($only !== []) {
    $unknown = array_diff($only, array_keys($jobs));
    if ($unknown !== []) {
      throw new \RuntimeException('Unknown asset(s): ' . implode(', ', $unknown));
    }
    $jobs = array_intersect_key($jobs, array_flip($only));
  }

  $tmp_dir = $workspace_dir . '/tmp';
  if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, TRUE);
  }

  foreach ($jobs as $name => $job) {
    $expect_script = $tmp_dir . '/' . $name . '.exp';
    $create_fn = $job['expect_fn'];
    if ($create_fn === 'create_command_expect_script') {
      $create_fn($expect_script, $job['script'], $job['command'], $job['env'] ?? []);
    }
    else {
      $create_fn($expect_script, $job['script']);
    }
  }

  $script_path = __FILE__;
  $failed = [];

  // Init and build run sequentially - init processes the workspace, build
  // assembles the Drupal codebase that lint and test need.
  foreach (array_filter(['init', 'build'], static fn(string $name): bool => isset($jobs[$name])) as $name) {
    info('--- Recording: ' . $name . ' ---');
    $result = run_worker($script_path, $name, $workspace_dir, $project_dir);
    if ($result['exit_code'] !== 0) {
      $failed[$name] = $result['output'];
      info('  FAILED: ' . $name);
    }
    else {
      info('  Done: ' . $name);
    }
    info('');
  }

  $parallel_jobs = array_values(array_filter(['lint', 'test'], static fn(string $name): bool => isset($jobs[$name])));
  $processes = [];
  $pipes_list = [];

  info('Launching ' . count($parallel_jobs) . ' workers in parallel...');
  info('');

  foreach ($parallel_jobs as $name) {
    $cmd = sprintf(
      'php %s --record %s --workspace %s',
      escapeshellarg($script_path),
      escapeshellarg($name),
      escapeshellarg($workspace_dir)
    );

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $pipes = [];
    $process = proc_open($cmd, $descriptors, $pipes, $project_dir);

    if (!is_resource($process)) {
      throw new \RuntimeException('Failed to launch worker for: ' . $name);
    }

    fclose($pipes[0]);

    $processes[$name] = $process;
    $pipes_list[$name] = $pipes;

    info('  Started: ' . $name);
  }

  info('');

  foreach ($processes as $name => $process) {
    $stdout = stream_get_contents($pipes_list[$name][1]);
    $stderr = stream_get_contents($pipes_list[$name][2]);
    fclose($pipes_list[$name][1]);
    fclose($pipes_list[$name][2]);

    $exit_code = proc_close($process);

    if ($exit_code !== 0) {
      $failed[$name] = trim(($stdout ?: '') . ($stderr ?: ''));
      info('  FAILED: ' . $name);
    }
    else {
      info('  Done: ' . $name);
    }
  }

  // Reset terminal - workers may leave it in raw mode.
  shell_exec('stty sane 2>/dev/null');

  info('');
  info('Cleaning up workspace: ' . $workspace_dir);
  remove_dir($workspace_dir);

  if (!empty($failed)) {
    info('');
    info('Errors:');
    foreach ($failed as $name => $output) {
      info('  ' . $name . ': ' . $output);
    }
    throw new \RuntimeException('Failed to generate ' . count($failed) . ' asset(s).');
  }

  info('');
  info('Done. SVG assets updated in ' . $assets_dir);
}

/**
 * Run a single worker synchronously and return its result.
 *
 * @param string $script_path
 *   Path to this script.
 * @param string $name
 *   The job name.
 * @param string $workspace_dir
 *   Path to the workspace directory.
 * @param string $cwd
 *   Working directory for the process.
 *
 * @return array{exit_code: int, output: string}
 *   The exit code and combined output.
 */
function run_worker(string $script_path, string $name, string $workspace_dir, string $cwd): array {
  $cmd = sprintf(
    'php %s --record %s --workspace %s 2>&1',
    escapeshellarg($script_path),
    escapeshellarg($name),
    escapeshellarg($workspace_dir)
  );

  $output = [];
  $exit_code = 0;
  exec($cmd, $output, $exit_code);

  return [
    'exit_code' => $exit_code,
    'output' => implode("\n", $output),
  ];
}

/**
 * Worker mode - process a single recording.
 *
 * @param string $name
 *   The job name to process.
 * @param string $workspace_dir
 *   Path to the workspace directory.
 */
function process_one(string $name, string $workspace_dir): void {
  $script_dir = dirname(__FILE__);
  $assets_dir = $script_dir;

  $jobs = get_jobs($workspace_dir);
  if (!isset($jobs[$name])) {
    throw new \RuntimeException('Unknown job: ' . $name);
  }

  $tmp_dir = $workspace_dir . '/tmp';
  $cast_file = $tmp_dir . '/' . $name . '.cast';
  $expect_script = $tmp_dir . '/' . $name . '.exp';
  $svg_file = $assets_dir . '/' . $name . '.svg';

  $job = $jobs[$name];
  $speed = (float) ($job['speed'] ?? 1.0);

  record_session($cast_file, $expect_script);
  post_process_cast($cast_file, $workspace_dir, $speed);
  convert_to_svg($cast_file, $svg_file, $assets_dir);
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
    throw new \RuntimeException('Failed to install svg-term: ' . ($output ?? 'unknown error'));
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
  if ($output === NULL && !file_exists($workspace_dir . '/init.php')) {
    throw new \RuntimeException('Failed to export git archive: ' . ($output ?? 'unknown error'));
  }

  return $workspace_dir;
}

/**
 * Record a session using asciinema with an expect script.
 *
 * @param string $cast_file
 *   Path to write the cast file.
 * @param string $expect_script
 *   Path to the expect script for automation.
 * @param int $rows
 *   Number of terminal rows.
 * @param int $cols
 *   Number of terminal columns.
 */
function record_session(string $cast_file, string $expect_script, int $rows = TERMINAL_ROWS, int $cols = TERMINAL_COLS): void {
  $cmd = sprintf(
    'asciinema rec --command=%s --window-size=%dx%d --idle-time-limit=%d --overwrite %s 2>&1',
    escapeshellarg($expect_script),
    $cols,
    $rows,
    MAX_IDLE_TIME,
    escapeshellarg($cast_file)
  );

  $output = [];
  $exit_code = 0;
  exec($cmd, $output, $exit_code);

  if (!file_exists($cast_file)) {
    throw new \RuntimeException('Failed to record session: ' . $cast_file . "\n" . implode("\n", $output));
  }

  if ($exit_code !== 0) {
    throw new \RuntimeException('Recording command failed with exit code ' . $exit_code . ': ' . $cast_file . "\n" . implode("\n", $output));
  }
}

/**
 * Create an expect script to automate init.php prompts.
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $playground_script
 *   Path to the init.php script.
 */
function create_init_expect_script(string $script_path, string $playground_script): void {
  $delay = PROMPT_DELAY;
  $content = <<<EXPECT
#!/usr/bin/env expect

set timeout 60
log_user 1

proc safe_send {s} {
    if {[exp_pid] > 0} {
        send -- \$s
    }
}

proc wait_and_enter {} {
    sleep {$delay}
    safe_send "\\r"
}

proc type_text {text} {
    set send_human {.1 .3 1 .05 2 .1 .2 0 .4 0 .6 0 .8 0 1}
    send -h \$text
}

proc arrow_down {} {
    sleep 0.3
    safe_send "\\033\[B"
}

cd [file dirname {$playground_script}]

set env(PS1) {\$ }
spawn bash --norc --noprofile

expect "\\$ "
sleep {$delay}
type_text "php init.php"
wait_and_enter

# Text: Extension name - type "Your Extension" and press enter.
expect "Extension name" {
    sleep {$delay}
    type_text "Your Extension"
    wait_and_enter
}

# Text: Machine name - accept placeholder default.
expect "Machine name" {
    wait_and_enter
}

# Select: Extension type - first option "Module" is pre-selected.
expect "Extension type" {
    sleep {$delay}
    wait_and_enter
}

# Multi-select: Target Drupal versions - all pre-checked by default; confirm
# with enter to keep all majors.
expect "Target Drupal versions" {
    sleep {$delay}
    safe_send "\\r"
}

# Multi-select: Command wrapper - select "Ahoy" (first option) with space,
# then confirm with enter.
expect "Command wrapper" {
    sleep {$delay}
    safe_send " "
    sleep 0.3
    safe_send "\\r"
}

# Multi-select: Tools - all pre-checked by default; confirm with enter to keep all.
expect "Tools" {
    sleep {$delay}
    safe_send "\\r"
}

# Confirm: Keep Cloudflare tunnel support - accept the default "Yes" with enter.
expect "Keep Cloudflare tunnel support" {
    sleep {$delay}
    safe_send "\\r"
}

# Confirm: Keep example lifecycle scripts - accept the default "No" with enter.
expect "Keep example lifecycle scripts" {
    sleep {$delay}
    safe_send "\\r"
}

# Confirm: Remove this script - type "y" to confirm.
expect "Remove this script" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

# Confirm: Proceed with project init - type "y" to confirm.
expect "Proceed" {
    sleep {$delay}
    type_text "y"
    wait_and_enter
}

# Wait for shell prompt after init completes, then exit.
expect "\\$ "
send "exit\\r"

expect eof
EXPECT;

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Create an expect script for a non-interactive command.
 *
 * Wraps the command in an expect script so it runs inside a PTY,
 * which is required for proper asciinema recording.
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $workspace_dir
 *   Path to the workspace directory.
 * @param string $command
 *   The command to run.
 * @param array<string, string> $env
 *   Environment variables to set before running the command.
 */
function create_command_expect_script(string $script_path, string $workspace_dir, string $command, array $env = []): void {
  $delay = PROMPT_DELAY;
  $env_lines = '';
  foreach ($env as $key => $value) {
    $env_lines .= 'set env(' . $key . ') {' . $value . '}' . "\n";
  }
  $content = <<<EXPECT
#!/usr/bin/env expect

set timeout 600
log_user 1

proc type_text {text} {
    set send_human {.1 .3 1 .05 2 .1 .2 0 .4 0 .6 0 .8 0 1}
    send -h \$text
}

cd {$workspace_dir}

{$env_lines}set env(PS1) {\$ }
spawn bash --norc --noprofile

expect "\\$ "
sleep {$delay}
type_text "{$command}"
sleep {$delay}
send "\\r"

# Wait for shell prompt after command completes, check exit code.
expect "\\$ "
send "echo __EXIT_CODE=\\\$?\\r"
expect -re {__EXIT_CODE=(\d+)}
set exit_code \$expect_out(1,string)
send "exit\\r"
expect eof

if {\$exit_code != 0} {
    puts stderr "Command '{$command}' failed with exit code \$exit_code"
    exit 1
}
EXPECT;

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Post-process a cast file.
 *
 * Removes the spawn command line and sanitizes paths.
 *
 * @param string $cast_file
 *   Path to the cast file.
 * @param string $workspace_dir
 *   Path to the workspace directory (to sanitize in output).
 * @param float $speed
 *   Speed multiplier for event timestamps (e.g. 2.0 = twice as fast).
 */
function post_process_cast(string $cast_file, string $workspace_dir, float $speed = 1.0): void {
  $content = file_get_contents($cast_file);
  if ($content === FALSE) {
    return;
  }

  $lines = explode("\n", $content);
  $filtered = [$lines[0]];
  for ($i = 1; $i < count($lines); $i++) {
    if (str_contains($lines[$i], 'spawn ')) {
      continue;
    }
    $filtered[] = $lines[$i];
  }

  if ($speed > 1.0) {
    foreach ($filtered as $idx => &$line) {
      if ($idx === 0) {
        continue;
      }
      $event = json_decode($line, TRUE);
      if (is_array($event) && isset($event[0]) && is_numeric($event[0])) {
        $event[0] = round((float) $event[0] / $speed, 6);
        $line = json_encode($event);
      }
    }
    unset($line);
  }

  // Add a pause at the end of the recording before the animation loops.
  $filtered[] = json_encode([END_PAUSE, 'o', ' ']);

  $content = implode("\n", $filtered);

  $content = str_replace($workspace_dir, '/home/user/project', $content);

  $home = getenv('HOME');
  if ($home !== FALSE && $home !== '') {
    $content = str_replace($home, '/home/user', $content);
  }

  file_put_contents($cast_file, $content);
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
  $renderer = $assets_dir . '/svg-term-render.js';

  $cmd = sprintf(
    'node %s %s %s --line-height 1.1 2>&1',
    escapeshellarg($renderer),
    escapeshellarg($cast_file),
    escapeshellarg($svg_file)
  );

  $output = shell_exec($cmd);

  if (!file_exists($svg_file) || filesize($svg_file) === 0) {
    throw new \RuntimeException('Failed to convert cast to SVG: ' . $cast_file . "\n" . ($output ?? ''));
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

  $cmd = sprintf('rm -rf %s 2>&1', escapeshellarg($directory));
  shell_exec($cmd);
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
  $record_index = array_search('--record', $argv);
  $workspace_index = array_search('--workspace', $argv);
  if ($record_index !== FALSE && isset($argv[$record_index + 1]) && $workspace_index !== FALSE && isset($argv[$workspace_index + 1])) {
    process_one($argv[$record_index + 1], $argv[$workspace_index + 1]);
  }
  else {
    // Orchestrator mode - optional positional asset names limit the run to a
    // subset (e.g. "init"); with none, every asset is regenerated.
    $only = array_values(array_filter(array_slice($argv, 1), static fn(string $arg): bool => !str_starts_with($arg, '-')));
    main($only);
  }
}
catch (\Exception $exception) {
  info('');
  info('ERROR: ' . $exception->getMessage());
  exit(1);
}
