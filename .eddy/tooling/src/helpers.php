<?php

/**
 * @file
 * Helper functions for the Eddy tooling commands.
 *
 * This file provides reusable PHP helper functions shared by the 'eddy-*'
 * command scripts, enabling consistent behavior across all tooling.
 *
 * ## Why We Use These Helpers
 *
 * These helper functions serve several critical purposes:
 *
 * 1. **Consistency**: Standardized output formatting (info, task, pass, fail)
 *    ensures all 'eddy-*' scripts produce uniform, recognizable messages.
 *
 * 2. **Reusability**: Common operations (files operations, command execution,
 *    etc.) are centralized to avoid code duplication.
 *
 * 3. **Testability**: All functions are designed to be mockable and testable,
 *    with comprehensive unit tests ensuring reliability.
 *
 * 4. **Maintainability**: Changes to core functionality (e.g., output
 *    formatting) only need to be made in one place.
 *
 * @phpcs:disable Drupal.NamingConventions.ValidFunctionName.InvalidName
 */

declare(strict_types=1);

namespace DrevOps\Eddy\Tooling;

// -----------------------------------------------------------------------------
// Output.
// -----------------------------------------------------------------------------

/**
 * Output a note message.
 */
function NOTE(string $format, string|int|float ...$args): void {
  echo sprintf('       %s%s', sprintf($format, ...$args), PHP_EOL);
}

/**
 * Output a task message.
 */
function TASK(string $format, string|int|float ...$args): void {
  print_tagged('[TASK]', '34', $format, ...$args);
}

/**
 * Output an info message.
 */
function INFO(string $format, string|int|float ...$args): void {
  print_tagged('[INFO]', '36', $format, ...$args);
}

/**
 * Output a success message.
 */
function PASS(string $format, string|int|float ...$args): void {
  print_tagged('[ OK ]', '32', $format, ...$args);
}

/**
 * Output a failure message.
 */
function FAIL(string $format, string|int|float ...$args): void {
  FAIL_NO_EXIT($format, ...$args);
  quit(1);
}

/**
 * Output a failure message and do not exit.
 */
function FAIL_NO_EXIT(string $format, string|int|float ...$args): void {
  print_tagged('[FAIL]', '31', $format, ...$args);
}

/**
 * Output a tagged line, coloured when the terminal supports it.
 *
 * @param string $tag
 *   The tag that opens the line, such as '[TASK]'.
 * @param string $color
 *   The ANSI code that styles the line, such as '34' for blue.
 * @param string $format
 *   The sprintf() format of the message.
 * @param string|int|float ...$args
 *   The values for the format.
 */
function print_tagged(string $tag, string $color, string $format, string|int|float ...$args): void {
  $message = sprintf($format, ...$args);

  echo term_supports_color() ? "\033[" . $color . 'm' . $tag . ' ' . $message . "\033[0m\n" : $tag . ' ' . $message . PHP_EOL;
}

/**
 * Check if terminal supports colors.
 */
function term_supports_color(): bool {
  return getenv('TERM') === 'dumb' || getenv('TERM') === FALSE ? FALSE : function_exists('posix_isatty') && @posix_isatty(STDOUT);
}

/**
 * Render a URL as a scannable QR code in the terminal.
 *
 * Uses `qrencode -t ANSIUTF8` to draw the code with Unicode block glyphs.
 * A missing `qrencode` binary fails rather than returning quietly, so a
 * requested code never goes undrawn without explanation.
 *
 * @param string $url
 *   The URL to encode. An empty string renders nothing, so an optional URL
 *   can be passed through without guarding first.
 */
function print_qrcode(string $url): void {
  if ($url === '') {
    return;
  }

  if (command_path('qrencode') === FALSE) {
    FAIL("Command 'qrencode' is not available. Install it from https://fukuchi.org/works/qrencode/ to render QR codes.");
  }

  passthru(sprintf('qrencode -t ANSIUTF8 %s', escapeshellarg($url)));
}

// -----------------------------------------------------------------------------
// Values.
// -----------------------------------------------------------------------------

/**
 * Split text into lines on any line ending.
 *
 * @param string $text
 *   The text to split.
 *
 * @return array<int, string>
 *   The lines.
 */
function split_lines(string $text): array {
  return preg_split('/\r\n|\r|\n/', $text) ?: [];
}

/**
 * Check whether a string holds a whole number of 1 or more.
 *
 * @param string $value
 *   The value to check.
 *
 * @return bool
 *   TRUE when the value is made of digits only and is 1 or more.
 */
function is_positive_integer(string $value): bool {
  return ctype_digit($value) && (int) $value >= 1;
}

/**
 * Validate that a value is a TCP port in the range 1-65535.
 *
 * Calls FAIL() with a descriptive message if validation fails.
 *
 * @param string $value
 *   The value to validate.
 * @param string $source
 *   Source name used in the error message (e.g. 'WEBSERVER_PORT').
 */
function validate_port_or_fail(string $value, string $source): void {
  if (!is_positive_integer($value) || (int) $value > 65535) {
    FAIL('Invalid %s "%s". Expected integer in range 1-65535.', $source, $value);
  }
}

/**
 * Validate that a value is a whole number of seconds, 1 or more.
 *
 * Calls FAIL() with a descriptive message if validation fails.
 *
 * @param string $value
 *   The value to validate.
 * @param string $source
 *   Source name used in the error message (e.g. 'WEBSERVER_WAIT_TIMEOUT').
 *
 * @return int
 *   The number of seconds.
 */
function validate_timeout_or_fail(string $value, string $source): int {
  if (!is_positive_integer($value)) {
    FAIL('Invalid %s "%s". Expected a whole number of seconds, 1 or more.', $source, $value);
  }

  return (int) $value;
}

// -----------------------------------------------------------------------------
// Environment variables and '.env'.
// -----------------------------------------------------------------------------

/**
 * Get environment variable with fallback and default value.
 */
function getenv_default(mixed ...$vars): string {
  if (count($vars) < 2) {
    throw new \InvalidArgumentException('getenv_default() requires at least 2 arguments: one or more variable names and a default value');
  }

  $default = array_pop($vars);

  foreach ($vars as $var) {
    $value = is_string($var) ? getenv($var) : $default;

    if ($value !== FALSE && is_string($value) && $value !== '') {
      return $value;
    }
  }

  return is_string($default) ? $default : '';
}

/**
 * Get required environment variable with fallback support.
 */
function getenv_or_fail(mixed ...$vars): string {
  if (count($vars) < 1) {
    throw new \InvalidArgumentException('getenv_or_fail() requires at least 1 argument');
  }

  $vars[] = '';

  $value = getenv_default(...$vars);

  if ($value !== '') {
    return $value;
  }

  FAIL('Missing required value for %s.', implode(', ', array_filter(array_map(static fn(mixed $v): string => is_string($v) ? $v : '', $vars))));

  // @codeCoverageIgnoreStart
  return '';
  // @codeCoverageIgnoreEnd
}

/**
 * Check if debug mode is enabled.
 *
 * @return bool
 *   TRUE when DEBUG is '1' in the environment or '.env'.
 */
function is_debug(): bool {
  return resolve_env_value('DEBUG', '')['value'] === '1';
}

/**
 * Read variables from a dotenv-style file into an associative array.
 *
 * Parses lines of the form KEY=VALUE. Lines that are blank or start with
 * '#' (after optional whitespace) are ignored. Surrounding single or double
 * quotes around the value are stripped. Keys and values are trimmed.
 *
 * @param string $dotenv_file
 *   Path to the dotenv file.
 *
 * @return array<string, string>
 *   Associative array of key-value pairs. Empty if the file is missing or
 *   unreadable, or contains no parseable lines.
 */
function dotenv_read(string $dotenv_file = '.env'): array {
  $vars = [];

  foreach (split_lines(file_read($dotenv_file)) as $line) {
    $trimmed = trim($line);

    if ($trimmed === '') {
      continue;
    }

    if (str_starts_with($trimmed, '#')) {
      continue;
    }

    if (!str_contains($trimmed, '=')) {
      continue;
    }

    [$key, $value] = explode('=', $trimmed, 2);
    $key = trim($key);
    $value = trim($value);

    if ($key === '') {
      continue;
    }

    if (strlen($value) >= 2) {
      $first = $value[0];
      $last = $value[strlen($value) - 1];

      if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
        $value = substr($value, 1, -1);
      }
    }

    $vars[$key] = $value;
  }

  return $vars;
}

/**
 * Set or update a variable in a dotenv-style file.
 *
 * If the key already exists in the file, its line is rewritten in place,
 * preserving the order of other lines and any comments or blank lines.
 * If the key does not exist, the assignment is appended to the end of the
 * file. The file is created with the single assignment if it does not exist.
 *
 * @param string $key
 *   Variable name to write.
 * @param string $value
 *   Variable value to write. Not quoted.
 * @param string $dotenv_file
 *   Path to the dotenv file.
 */
function dotenv_write_var(string $key, string $value, string $dotenv_file = '.env'): void {
  $assignment = sprintf('%s=%s', $key, $value);

  if (!is_file($dotenv_file)) {
    file_write_or_fail($dotenv_file, $assignment . PHP_EOL);

    return;
  }

  $content = file_read_or_fail($dotenv_file);
  $lines = split_lines($content);
  $trailing_newline = $content !== '' && (str_ends_with($content, "\n") || str_ends_with($content, "\r"));

  if ($trailing_newline && end($lines) === '') {
    array_pop($lines);
  }

  // Replace the LAST matching assignment so dotenv_read()'s
  // last-assignment-wins semantics agree with what is written.
  $replace_index = NULL;

  foreach ($lines as $i => $line) {
    $trimmed = trim($line);

    if ($trimmed === '') {
      continue;
    }

    if (str_starts_with($trimmed, '#')) {
      continue;
    }

    if (!str_contains($trimmed, '=')) {
      continue;
    }

    [$existing_key] = explode('=', $trimmed, 2);

    if (trim($existing_key) === $key) {
      $replace_index = $i;
    }
  }

  if ($replace_index === NULL) {
    $lines[] = $assignment;
  }
  else {
    $lines[$replace_index] = $assignment;
  }

  file_write_or_fail($dotenv_file, implode(PHP_EOL, $lines) . PHP_EOL);
}

/**
 * Remove a variable from a dotenv-style file.
 *
 * Every assignment of the key is removed, so dotenv_read() no longer returns
 * it. Comments, blank lines and other variables keep their order. A missing
 * file, or one that does not assign the key, is left untouched.
 *
 * @param string $key
 *   Variable name to remove.
 * @param string $dotenv_file
 *   Path to the dotenv file.
 */
function dotenv_unset_var(string $key, string $dotenv_file = '.env'): void {
  $lines = split_lines(file_read($dotenv_file));
  $kept = preg_grep('/^\s*' . preg_quote($key, '/') . '\s*=/', $lines, PREG_GREP_INVERT) ?: [];

  if (count($kept) === count($lines)) {
    return;
  }

  file_write_or_fail($dotenv_file, implode(PHP_EOL, $kept));
}

/**
 * Resolve an environment value from env, then dotenv, then default.
 *
 * Mirrors the precedence chain used by the start, stop, provision, and
 * info scripts: shell environment first, then a matching key in the
 * dotenv file, then the supplied default. Returns the resolved value
 * together with a source label identifying which leg of the chain
 * produced it.
 *
 * @param string $name
 *   Variable name to resolve.
 * @param string $default
 *   Value to return when neither env nor the dotenv file provides a
 *   non-empty value. May be an empty string when callers want to
 *   detect that case and apply their own fallback (for example,
 *   resolve_port_value() when auto-discovery is requested).
 * @param string $dotenv_file
 *   Path to the dotenv file to consult.
 *
 * @return array{value: string, source: string}
 *   Resolved value together with a source label: 'env' when the value
 *   came from the shell environment, the dotenv file path when the value
 *   came from that file, or 'default' when neither produced a value.
 */
function resolve_env_value(string $name, string $default, string $dotenv_file = '.env'): array {
  $env = getenv($name);

  if ($env !== FALSE && $env !== '') {
    return ['value' => $env, 'source' => 'env'];
  }

  $dotenv = dotenv_read($dotenv_file);

  if (isset($dotenv[$name]) && $dotenv[$name] !== '') {
    return ['value' => $dotenv[$name], 'source' => $dotenv_file];
  }

  return ['value' => $default, 'source' => 'default'];
}

// -----------------------------------------------------------------------------
// Files and directories.
// -----------------------------------------------------------------------------

/**
 * Read a file that may be missing.
 *
 * @param string $file
 *   The file path.
 *
 * @return string
 *   The content, or an empty string when the file is missing or unreadable.
 *   An unreadable file also raises PHP's warning.
 */
function file_read(string $file): string {
  if (!is_file($file)) {
    return '';
  }

  $content = file_get_contents($file);

  return $content === FALSE ? '' : $content;
}

/**
 * Read a file, failing when it is missing or unreadable.
 *
 * @param string $file
 *   The file path.
 *
 * @return string
 *   The content.
 */
function file_read_or_fail(string $file): string {
  $content = is_file($file) ? file_get_contents($file) : FALSE;

  if ($content === FALSE) {
    FAIL('Unable to read %s.', $file);

    // @codeCoverageIgnoreStart
    return '';
    // @codeCoverageIgnoreEnd
  }

  return $content;
}

/**
 * Write a file, failing when it cannot be written.
 *
 * @param string $file
 *   The file path.
 * @param string $content
 *   The content to write.
 * @param int $flags
 *   The flags of file_put_contents(), such as FILE_APPEND.
 */
function file_write_or_fail(string $file, string $content, int $flags = 0): void {
  if (file_put_contents($file, $content, $flags) === FALSE) {
    FAIL('Unable to write %s.', $file);
  }
}

/**
 * Read a JSON file into an array.
 *
 * @param string $file
 *   The path to the JSON file.
 *
 * @return array<mixed>
 *   The decoded array, or an empty array when the file is missing or
 *   unreadable, or does not hold a JSON object or array.
 */
function json_file(string $file): array {
  $decoded = json_decode(file_read($file), TRUE);

  return is_array($decoded) ? $decoded : [];
}

/**
 * Write an array to a JSON file, failing when the file cannot be written.
 *
 * The JSON is pretty-printed with unescaped slashes and ends with a newline.
 *
 * @param string $file
 *   The path to the JSON file.
 * @param array<mixed> $data
 *   The data to encode.
 */
function json_file_write(string $file, array $data): void {
  file_write_or_fail($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
}

/**
 * Replace content in a file using a regular expression.
 *
 * Fails when the file cannot be read or written, or the pattern is invalid.
 *
 * @return string
 *   The file content after replacement.
 */
function replace_in_file(string $file, string $pattern, string $replacement): string {
  $content = file_read_or_fail($file);
  $replaced = preg_replace($pattern, $replacement, $content);

  if ($replaced === NULL) {
    FAIL('Regex replacement failed in file %s with pattern %s.', $file, $pattern);

    // @codeCoverageIgnoreStart
    return $content;
    // @codeCoverageIgnoreEnd
  }

  file_write_or_fail($file, $replaced);

  return $replaced;
}

/**
 * Copy a file, failing when it cannot be copied.
 *
 * @param string $source
 *   The file to copy.
 * @param string $destination
 *   The path of the copy.
 */
function copy_or_fail(string $source, string $destination): void {
  if (!copy($source, $destination)) {
    FAIL('Unable to copy %s to %s.', $source, $destination);
  }
}

/**
 * Create a symlink, failing when it cannot be created.
 *
 * @param string $target
 *   The path that the symlink points to.
 * @param string $link
 *   The path of the symlink.
 */
function symlink_or_fail(string $target, string $link): void {
  if (!symlink($target, $link)) {
    FAIL('Unable to create symlink %s to %s.', $link, $target);
  }
}

/**
 * Create a directory, failing when it cannot be created.
 *
 * A directory that already exists, or that another process creates
 * concurrently, counts as created.
 *
 * @param string $dir
 *   The directory path.
 * @param int $mode
 *   The permissions of each directory created, before the umask applies. A
 *   directory that already exists keeps its permissions.
 */
function mkdir_or_fail(string $dir, int $mode = 0755): void {
  if (!@mkdir($dir, $mode, TRUE) && !is_dir($dir)) {
    FAIL('Unable to create directory %s.', $dir);
  }
}

/**
 * Recursively copy a directory.
 *
 * Fails when a directory or a file cannot be copied.
 */
function copy_dir(string $src, string $dst): void {
  mkdir_or_fail($dst);

  $iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
    \RecursiveIteratorIterator::SELF_FIRST
  );

  /** @var \RecursiveDirectoryIterator $item */
  foreach ($iterator as $item) {
    $target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathname();

    if ($item->isDir()) {
      mkdir_or_fail($target);
    }
    else {
      copy_or_fail($item->getPathname(), $target);
    }
  }
}

/**
 * Recursively remove a directory.
 *
 * @return bool
 *   TRUE when the directory was removed or did not exist.
 */
function remove_dir(string $dir): bool {
  if (!is_dir($dir)) {
    return TRUE;
  }

  $items = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS),
    \RecursiveIteratorIterator::CHILD_FIRST
  );

  /** @var \SplFileInfo $item */
  foreach ($items as $item) {
    $path = $item->getPathname();

    if (is_link($path)) {
      @unlink($path);
    }
    elseif ($item->isDir()) {
      @rmdir($path);
    }
    else {
      @unlink($path);
    }
  }

  return @rmdir($dir);
}

/**
 * Recursively chmod a directory.
 */
function chmod_recursive(string $dir, int $mode): void {
  if (!is_dir($dir)) {
    return;
  }

  $iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS),
    \RecursiveIteratorIterator::SELF_FIRST
  );

  /** @var \SplFileInfo $item */
  foreach ($iterator as $item) {
    if (!is_link($item->getPathname())) {
      @chmod($item->getPathname(), $mode);
    }
  }

  @chmod($dir, $mode);
}

const LOGS_DIR = '.logs';

// -----------------------------------------------------------------------------
// Commands and processes.
// -----------------------------------------------------------------------------

/**
 * Get the path to a command, or FALSE if the command does not exist.
 */
function command_path(string $command): string|false {
  if (!preg_match('/^[A-Za-z0-9_\-]+(?: [A-Za-z0-9_\-]+)*$/', $command)) {
    return FALSE;
  }

  exec(sprintf('command -v %s 2>/dev/null', $command), $output, $exit_code);

  return $exit_code === 0 && !empty($output[0]) ? trim($output[0]) : FALSE;
}

/**
 * Require a command to be available, or fail.
 */
function command_exists_or_fail(string $command): void {
  if (command_path($command) === FALSE) {
    FAIL("Command '%s' is not available.", $command);
  }
}

/**
 * Run a command and capture its output, killing it after a time limit.
 *
 * The command runs without a shell. Its standard input reads from '/dev/null'
 * and its standard error is discarded.
 *
 * @param list<string> $command
 *   The executable followed by its arguments.
 * @param int $timeout
 *   Seconds to wait for the command to exit.
 *
 * @return string|null
 *   The standard output, or NULL when the command was still running at the
 *   time limit. A command that cannot be started returns an empty string.
 */
function command_output(array $command, int $timeout): ?string {
  $pipes = [];
  $process = @proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);

  if (!is_resource($process)) {
    return '';
  }

  // A blocking read waits until the command closes its output, so a hung
  // command would block the loop past the time limit.
  stream_set_blocking($pipes[1], FALSE);
  $output = '';

  for ($i = 0; $i < $timeout * 10 && proc_get_status($process)['running']; $i++) {
    $output .= (string) stream_get_contents($pipes[1]);
    usleep(100000);
  }

  $timed_out = proc_get_status($process)['running'];

  if ($timed_out) {
    // A command can ignore SIGTERM, and proc_close() waits for it to exit, so
    // send SIGKILL. The SIGKILL constant needs the pcntl extension.
    proc_terminate($process, 9);
  }

  // Output written after the last read in the loop is still in the pipe.
  $output .= (string) stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  proc_close($process);

  return $timed_out ? NULL : $output;
}

/**
 * Run a command and capture its combined stdout and stderr.
 *
 * The command is wrapped in a brace group so both streams are captured
 * together and the exit status is preserved, even for compound commands.
 *
 * @param string $command
 *   The command to run.
 * @param int|null &$exit_code
 *   Populated with the command's exit code.
 *
 * @param-out int $exit_code
 *
 * @return string
 *   The captured combined output.
 */
function passthru_capture(string $command, ?int &$exit_code = NULL): string {
  ob_start();
  passthru('{ ' . $command . '; } 2>&1', $exit_code);

  return ob_get_clean() ?: '';
}

/**
 * Run a command via passthru, failing if exit code is non-zero.
 *
 * Command output is suppressed during a normal run and surfaced only when the
 * command fails; set DEBUG=1 to stream it live instead.
 */
function passthru_or_fail(string $command, string $format = '', string|int|float ...$args): void {
  $exit_code = 0;

  if (is_debug()) {
    passthru($command, $exit_code);
  }
  else {
    $output = passthru_capture($command, $exit_code);

    // Surface the captured output only on failure so the error stays
    // diagnosable while a successful run remains quiet.
    if ($exit_code !== 0) {
      echo $output;
    }
  }

  if ($exit_code !== 0) {
    // Surface the failure message without exiting here so the quit() below
    // always preserves the command's real exit code, whether or not a
    // message was supplied.
    if ($format !== '') {
      FAIL_NO_EXIT($format, ...$args);
    }

    quit($exit_code);
  }
}

/**
 * Run a command via passthru, always showing output, failing on non-zero exit.
 *
 * The counterpart to passthru_or_fail(): here the command's output is always
 * streamed to the terminal rather than suppressed, for commands whose output
 * is meaningful in its own right rather than dependency-tool noise.
 */
function passthru_verbose_or_fail(string $command, string $format = '', string|int|float ...$args): void {
  $exit_code = 0;
  passthru($command, $exit_code);

  if ($exit_code !== 0) {
    // Surface the failure message without exiting here so the quit() below
    // always preserves the command's real exit code, whether or not a
    // message was supplied.
    if ($format !== '') {
      FAIL_NO_EXIT($format, ...$args);
    }

    quit($exit_code);
  }
}

/**
 * Run custom shell scripts from a directory matching a filename prefix.
 *
 * Scripts are matched as "<dir>/<prefix>*.sh", sorted lexicographically,
 * and executed in order. Each script runs with the current working
 * directory as the parent process's CWD and inherits the parent's
 * environment. A non-zero exit from any script aborts the parent.
 *
 * The directory and any matching scripts are optional: a missing
 * directory or an empty match set returns silently. This is the extension
 * point behind the assemble, provision, start and stop hooks.
 *
 * @param string $dir
 *   Directory to scan, relative to the current working directory.
 * @param string $prefix
 *   Filename prefix to match (e.g. "assemble-" or "provision-").
 */
function run_custom_scripts(string $dir, string $prefix): void {
  if (!is_dir($dir)) {
    return;
  }

  $files = glob($dir . '/' . $prefix . '*.sh') ?: [];

  if ($files === []) {
    return;
  }

  sort($files);

  foreach ($files as $file) {
    if (!is_file($file)) {
      continue;
    }

    TASK("Running custom script '%s'.", $file);
    // Show the hook's output - these scripts are the project's own and their
    // output is intentional, unlike the suppressed dependency-tool commands.
    passthru_verbose_or_fail(escapeshellarg($file), "Custom script '%s' failed.", $file);
    PASS("Completed custom script '%s'.", $file);
  }
}

/**
 * Get the command line of a process.
 *
 * @param int $pid
 *   The process ID.
 *
 * @return string
 *   The command line, or an empty string when no such process runs.
 */
function process_command(int $pid): string {
  return trim((string) shell_exec(sprintf('ps -p %d -o command= 2>/dev/null', $pid)));
}

/**
 * Get the working directory of a process.
 *
 * @param int $pid
 *   The process ID.
 *
 * @return string
 *   The absolute path, or an empty string when it cannot be read.
 */
function process_cwd(int $pid): string {
  $output = (string) shell_exec(sprintf('lsof -a -p %d -d cwd -Fn 2>/dev/null', $pid));

  return preg_match('/^n(.+)$/m', $output, $matches) === 1 ? $matches[1] : '';
}

/**
 * Kill processes with SIGKILL.
 *
 * Process IDs below 1 are ignored, because 'kill' signals a whole process
 * group for 0 and negative IDs.
 *
 * @param array<int, int> $pids
 *   The process IDs.
 */
function kill_processes(array $pids): void {
  $pids = array_filter($pids, static fn(int $pid): bool => $pid > 0);

  if ($pids === []) {
    return;
  }

  passthru(sprintf('kill -9 %s 2>/dev/null', implode(' ', $pids)));
}

// -----------------------------------------------------------------------------
// Ports.
// -----------------------------------------------------------------------------

/**
 * Find a free TCP port by scanning a range of ports.
 *
 * Each port is probed by attempting a client connect to localhost. A
 * connection that succeeds means some server is already listening on
 * that port (on either IPv4 or IPv6, whichever the resolver picks).
 * A connection that is refused means the port is free. This works on
 * environments without IPv6 (e.g. Docker-based CI) where a server-side
 * bind probe to [::1] would always fail. Calls FAIL() if no free port
 * is found within $max_attempts.
 *
 * @param int $start
 *   Port number to start scanning from.
 * @param int $max_attempts
 *   Maximum number of ports to try.
 *
 * @return int
 *   The first free port found.
 */
function find_free_port(int $start = 8000, int $max_attempts = 100): int {
  if ($start < 1 || $start > 65535) {
    FAIL('Start port must be between 1 and 65535, got %d.', $start);
  }

  if ($max_attempts < 1) {
    FAIL('Max attempts must be a positive integer, got %d.', $max_attempts);
  }

  for ($port = $start; $port < $start + $max_attempts; $port++) {
    $fp = @stream_socket_client(sprintf('tcp://localhost:%d', $port), $errno, $errstr, 0.2);

    if ($fp === FALSE) {
      return $port;
    }

    fclose($fp);
  }

  FAIL('Unable to find a free port in range %d-%d.', $start, $start + $max_attempts - 1);

  // @codeCoverageIgnoreStart
  return $start;
  // @codeCoverageIgnoreEnd
}

/**
 * Resolve a port variable, auto-discovering and persisting one when unset.
 *
 * Shared by resolve_webserver() and resolve_webdriver_port(): both resolve
 * a named port variable via the env -> dotenv -> default chain, and, when
 * auto-discovery is requested and neither source supplies a value,
 * allocate a free port starting from $scan_start and persist it back to
 * the dotenv file so repeat runs reuse the same port.
 *
 * @param string $name
 *   Variable name to resolve (e.g. 'WEBSERVER_PORT').
 * @param string $default
 *   Value to use when auto-discovery is disabled and neither env nor the
 *   dotenv file supplies one.
 * @param bool $auto_discover
 *   When TRUE and the port resolves from neither env nor dotenv, discover
 *   a free port via find_free_port() and persist it via
 *   dotenv_write_var(). The reported source then becomes the dotenv file
 *   path because that is where the value now lives.
 * @param int $scan_start
 *   Port number to start the free-port scan from when auto-discovery
 *   kicks in.
 * @param string $dotenv_file
 *   Path to the dotenv file to read and (optionally) write.
 *
 * @return array{value: string, source: string}
 *   Resolved port and source, in the same shape as resolve_env_value(),
 *   with source updated to the dotenv file path when auto-discovery
 *   persisted a value.
 */
function resolve_port_value(string $name, string $default, bool $auto_discover, int $scan_start, string $dotenv_file = '.env'): array {
  $default_port = $auto_discover ? '' : $default;
  ['value' => $port, 'source' => $port_source] = resolve_env_value($name, $default_port, $dotenv_file);

  if ($auto_discover && $port_source === 'default') {
    $port = (string) find_free_port($scan_start);
    dotenv_write_var($name, $port, $dotenv_file);
    $port_source = $dotenv_file;
  }

  return ['value' => $port, 'source' => $port_source];
}

/**
 * Wait for a TCP port to accept connections.
 *
 * Each probe follows a 100-millisecond pause, the first one included. The
 * pause and the connection attempt are both capped at the time left, so the
 * wait ends at the timeout even when the host drops connection attempts.
 *
 * @param string $host
 *   The host to connect to.
 * @param string $port
 *   The TCP port.
 * @param int $timeout
 *   The number of seconds to wait. A value below 1 allows 1 probe, with no
 *   pause and no time to connect.
 *
 * @return bool
 *   TRUE once the port accepts a connection, FALSE when no probe connects.
 */
function wait_for_port(string $host, string $port, int $timeout): bool {
  $interval = 0.1;
  // cspell:ignore hrtime
  // hrtime() is monotonic, so a change of the system time cannot move the
  // deadline.
  $deadline = hrtime(TRUE) / 1e9 + $timeout;
  $time_left = static fn(): float => max(0.0, $deadline - hrtime(TRUE) / 1e9);

  do {
    usleep((int) round(min($interval, $time_left()) * 1e6));

    $fp = @fsockopen($host, (int) $port, $errno, $errstr, min($interval, $time_left()));

    if ($fp !== FALSE) {
      fclose($fp);

      return TRUE;
    }
  } while ($time_left() > 0);

  return FALSE;
}

/**
 * Build the command that prints the IDs of the processes listening on a port.
 *
 * Only a listening TCP socket matches, so a process that holds a client
 * connection to the port, such as a browser, is left out.
 *
 * @param int|string $port
 *   The TCP port.
 *
 * @return string
 *   The 'lsof' command. It discards its own errors.
 */
function port_listener_pids_command(int|string $port): string {
  return sprintf('lsof -ti tcp:%s -sTCP:LISTEN 2>/dev/null', escapeshellarg((string) $port));
}

/**
 * List the processes listening on a port, split by owner.
 *
 * @param string $port
 *   The TCP port.
 * @param callable(int, string): bool $owns
 *   Receives the ID and the command line of a process, which is empty when it
 *   cannot be read, and returns TRUE for a process the caller owns.
 *
 * @return array{own: array<int, string>, other: array<int, string>}
 *   The command line of each listening process, keyed by process ID.
 */
function port_listeners(string $port, callable $owns): array {
  $listeners = ['own' => [], 'other' => []];
  $values = preg_split('/\s+/', trim((string) shell_exec(port_listener_pids_command($port))), -1, PREG_SPLIT_NO_EMPTY) ?: [];

  foreach ($values as $value) {
    if (!is_positive_integer($value)) {
      continue;
    }

    $pid = (int) $value;
    $command = process_command($pid);
    $listeners[$owns($pid, $command) ? 'own' : 'other'][$pid] = $command;
  }

  return $listeners;
}

/**
 * Report the processes that hold a port, then quit.
 *
 * @param string $port
 *   The TCP port.
 * @param string $variable
 *   The '.env' variable that selects the port, such as 'WEBSERVER_PORT'.
 * @param array<int, string> $listeners
 *   The command line of each process holding the port, keyed by process ID.
 */
function fail_port_in_use(string $port, string $variable, array $listeners): void {
  FAIL_NO_EXIT('Port %s is in use by another process. Stop it, or set %s in .env to a free port.', $port, $variable);

  foreach ($listeners as $pid => $command) {
    NOTE('%d %s', $pid, $command);
  }

  quit(1);
}

/**
 * Report the processes that were left running on a port.
 *
 * @param string $port
 *   The TCP port.
 * @param array<int, string> $listeners
 *   The command line of each process left running, keyed by process ID.
 */
function note_port_in_use(string $port, array $listeners): void {
  if ($listeners === []) {
    return;
  }

  NOTE('Port %s is still in use by another process, which was left running:', $port);

  foreach ($listeners as $pid => $command) {
    NOTE('%d %s', $pid, $command);
  }
}

// -----------------------------------------------------------------------------
// Webserver.
// -----------------------------------------------------------------------------

/**
 * Resolve the webserver host and port with source tracking.
 *
 * Resolves 'WEBSERVER_HOST' via resolve_env_value() and
 * 'WEBSERVER_PORT' via resolve_port_value(). Optionally auto-discovers
 * a free port when neither env nor the dotenv file provides one,
 * persisting the discovered port back to the dotenv file. Optionally
 * validates that the resolved port is a valid TCP port number.
 *
 * @param bool $auto_discover
 *   When TRUE and the port resolves from neither env nor dotenv,
 *   discover a free port via find_free_port() and persist it via
 *   dotenv_write_var(). The reported port source then becomes the
 *   dotenv file path because that is where the value now lives.
 * @param bool $validate_port
 *   When TRUE, call validate_port_or_fail() on the resolved port and
 *   abort if it is not a valid TCP port. The info script disables
 *   this so a malformed value can be surfaced in the output rather
 *   than crashing the read-only summary.
 * @param string $dotenv_file
 *   Path to the dotenv file to read and (optionally) write.
 *
 * @return array{host: string, host_source: string, port: string, port_source: string}
 *   The resolved host and port together with the source label for
 *   each: 'env' when the value came from the shell environment, the
 *   dotenv file path when it came from that file, or 'default' when
 *   the supplied default was used.
 */
function resolve_webserver(bool $auto_discover = FALSE, bool $validate_port = TRUE, string $dotenv_file = '.env'): array {
  ['value' => $host, 'source' => $host_source] = resolve_env_value('WEBSERVER_HOST', 'localhost', $dotenv_file);
  ['value' => $port, 'source' => $port_source] = resolve_port_value('WEBSERVER_PORT', '8000', $auto_discover, 8000, $dotenv_file);

  if ($validate_port) {
    validate_port_or_fail($port, 'WEBSERVER_PORT');
  }

  return [
    'host' => $host,
    'host_source' => $host_source,
    'port' => $port,
    'port_source' => $port_source,
  ];
}

/**
 * Resolve the public site URL, preferring an active tunnel URL.
 *
 * Reads TUNNEL_URL straight from the dotenv file rather than the process
 * environment. A wrapping Make or Ahoy invocation loads '.env' into the
 * environment once at startup, so a TUNNEL_URL written to '.env' during the
 * same run would otherwise be masked by the stale exported value. Falls back
 * to the 'http://host:port' form when no tunnel URL is set.
 *
 * @param string $host
 *   Webserver host for the fallback URL.
 * @param string $port
 *   Webserver port for the fallback URL.
 * @param string $dotenv_file
 *   Path to the dotenv file to consult for TUNNEL_URL.
 *
 * @return string
 *   The TUNNEL_URL value when the dotenv file defines a non-empty one,
 *   otherwise 'http://<host>:<port>'.
 */
function resolve_site_url(string $host, string $port, string $dotenv_file = '.env'): string {
  $tunnel_url = dotenv_read($dotenv_file)['TUNNEL_URL'] ?? '';

  return $tunnel_url !== '' ? $tunnel_url : sprintf('http://%s:%s', $host, $port);
}

/**
 * Get the path that a PHP webserver command line passes to '-t'.
 *
 * The command line is split on whitespace, so a path with spaces is cut at
 * the first one.
 *
 * @param string $command
 *   The command line, as 'ps' prints it.
 *
 * @return string|null
 *   The path, as given, or NULL when the command line has no '-t' value.
 */
function webserver_docroot_argument(string $command): ?string {
  $arguments = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY) ?: [];

  foreach ($arguments as $index => $argument) {
    if ($argument === '-t') {
      return $arguments[$index + 1] ?? NULL;
    }

    if (str_starts_with($argument, '-t')) {
      return substr($argument, 2);
    }
  }

  return NULL;
}

/**
 * Check whether a process serves a docroot with the PHP webserver.
 *
 * The command line passes the docroot to '-t', either the way 'eddy-start'
 * launches the webserver or through a path that resolves to it: a symlink, or
 * a relative path, which the process resolves against its working directory.
 *
 * @param string $command
 *   The command line of the process, as 'ps' prints it.
 * @param string $docroot
 *   The absolute path of the docroot.
 * @param int $pid
 *   The process ID, used to read the working directory for a relative path.
 *   0 leaves a relative path unresolved.
 *
 * @return bool
 *   TRUE when the process serves the docroot.
 */
function webserver_serves(string $command, string $docroot, int $pid = 0): bool {
  if (preg_match('#\s-t ' . preg_quote($docroot, '#') . '(?:\s|$)#', $command) === 1) {
    return TRUE;
  }

  $path = webserver_docroot_argument($command);

  if ($path === NULL || $path === '') {
    return FALSE;
  }

  if (!str_starts_with($path, '/')) {
    $cwd = $pid > 0 ? process_cwd($pid) : '';

    if ($cwd === '') {
      return FALSE;
    }

    $path = $cwd . '/' . $path;
  }

  $resolved = realpath($path);

  return $resolved !== FALSE && $resolved === realpath($docroot);
}

/**
 * List the processes listening on the webserver port, split by owner.
 *
 * A process whose command line cannot be read counts as the project's own, so
 * the port is still freed where 'ps' is missing.
 *
 * @param string $port
 *   The webserver port.
 * @param string $docroot
 *   The absolute path of the project's docroot.
 *
 * @return array{own: array<int, string>, other: array<int, string>}
 *   The command line of each listening process, keyed by process ID: 'own'
 *   for the processes that serve the docroot, and 'other' for the rest.
 */
function webserver_listeners(string $port, string $docroot): array {
  return port_listeners($port, static fn(int $pid, string $command): bool => $command === '' || webserver_serves($command, $docroot, $pid));
}

/**
 * Detect the XDebug state of the project's webserver.
 *
 * Inspects the command line of the webserver that serves the docroot for the
 * 'xdebug.mode=debug' flag. Another process listening on the port is left
 * out, so its flags never stand in for the project's server.
 *
 * @param string $port
 *   The webserver port.
 * @param string $docroot
 *   The absolute path of the project's docroot.
 *
 * @return string
 *   'enabled' or 'disabled' while the project's webserver listens on the port,
 *   and '-' otherwise.
 */
function xdebug_state(string $port, string $docroot): string {
  $own = webserver_listeners($port, $docroot)['own'];

  if ($own === []) {
    return '-';
  }

  return str_contains(reset($own), 'xdebug.mode=debug') ? 'enabled' : 'disabled';
}

// -----------------------------------------------------------------------------
// WebDriver and Selenium.
// -----------------------------------------------------------------------------

/**
 * Resolve the WebDriver endpoint port with source tracking.
 *
 * Mirrors the port half of resolve_webserver() for 'WEBDRIVER_PORT':
 * shell env first, then a matching key in the dotenv file, then the
 * default. With auto_discover and no configured value, a free port is
 * allocated from 4444 and persisted to the dotenv file so that several
 * projects each get their own WebDriver endpoint and never contend
 * for a single port.
 *
 * @param bool $auto_discover
 *   When TRUE and the port resolves from neither env nor dotenv, discover
 *   a free port via find_free_port() and persist it via dotenv_write_var().
 *   The reported source then becomes the dotenv file path.
 * @param bool $validate_port
 *   When TRUE, call validate_port_or_fail() on the resolved port and abort
 *   if it is not a valid TCP port. The info script disables this so a
 *   malformed value can be surfaced in the output rather than crashing the
 *   read-only summary.
 * @param string $dotenv_file
 *   Path to the dotenv file to read and (optionally) write.
 *
 * @return array{port: string, port_source: string}
 *   The resolved port and its source label: 'env', the dotenv file path,
 *   or 'default'.
 */
function resolve_webdriver_port(bool $auto_discover = FALSE, bool $validate_port = TRUE, string $dotenv_file = '.env'): array {
  ['value' => $port, 'source' => $port_source] = resolve_port_value('WEBDRIVER_PORT', '4444', $auto_discover, 4444, $dotenv_file);

  if ($validate_port) {
    validate_port_or_fail($port, 'WEBDRIVER_PORT');
  }

  return [
    'port' => $port,
    'port_source' => $port_source,
  ];
}

/**
 * List the processes listening on the WebDriver port, split by owner.
 *
 * 'eddy-browser-start' launches chromedriver from the project root. A process
 * is the project's own when its command line starts with a 'chromedriver'
 * executable and its working directory resolves to the root.
 *
 * 'ps' prints the command line unquoted, so an executable path with spaces
 * does not match. A process whose command line or working directory cannot be
 * read counts as another's, so it is never stopped.
 *
 * @param string $port
 *   The WebDriver port.
 * @param string $root
 *   The absolute path of the project root.
 *
 * @return array{own: array<int, string>, other: array<int, string>}
 *   The command line of each listening process, keyed by process ID.
 */
function webdriver_listeners(string $port, string $root): array {
  $resolved_root = realpath($root) ?: $root;

  return port_listeners($port, static function (int $pid, string $command) use ($resolved_root): bool {
    if (preg_match('#^(?:\S*/)?chromedriver(?:\s|$)#', $command) !== 1) {
      return FALSE;
    }

    $cwd = process_cwd($pid);

    return $cwd !== '' && (realpath($cwd) ?: $cwd) === $resolved_root;
  });
}

const SELENIUM_CONTAINER_PREFIX = 'selenium';

const SELENIUM_PROJECT_LABEL = 'eddy.project';

/**
 * Build the container name for the Selenium endpoint on the given port.
 *
 * Projects running side by side use different ports, so naming the container
 * after the port keeps their containers apart. The SELENIUM_PROJECT_LABEL
 * label tells apart 2 projects that recorded the same port.
 *
 * @param string $port
 *   The WebDriver port the container publishes.
 *
 * @return string
 *   The container name, such as 'selenium-4444'.
 */
function selenium_container(string $port): string {
  return SELENIUM_CONTAINER_PREFIX . '-' . $port;
}

/**
 * Read a field of the Selenium container for a port through 'docker inspect'.
 *
 * @param string $port
 *   The WebDriver port the container publishes.
 * @param string $format
 *   The Go template that 'docker inspect --format' renders.
 *
 * @return string
 *   The output, or an empty string when the container does not exist.
 */
function selenium_container_inspect(string $port, string $format): string {
  return (string) shell_exec(sprintf('docker inspect --format %s %s 2>/dev/null', escapeshellarg($format), escapeshellarg(selenium_container($port))));
}

/**
 * Get the project that started the Selenium container for a port.
 *
 * @param string $port
 *   The WebDriver port the container publishes.
 *
 * @return string|null
 *   The project root in the container's SELENIUM_PROJECT_LABEL label, an empty
 *   string for a container without the label, or NULL when no such container
 *   exists.
 */
function selenium_container_project(string $port): ?string {
  $output = selenium_container_inspect($port, sprintf('{{.Name}}|{{index .Config.Labels "%s"}}', SELENIUM_PROJECT_LABEL));
  $separator = strpos($output, '|');

  if ($separator === FALSE) {
    return NULL;
  }

  $project = trim(substr($output, $separator + 1));

  return $project === '<no value>' ? '' : $project;
}

/**
 * Check whether the Selenium container for a port is running.
 *
 * @param string $port
 *   The WebDriver port the container publishes.
 *
 * @return bool
 *   TRUE when the container exists and is running.
 */
function selenium_container_running(string $port): bool {
  return trim(selenium_container_inspect($port, '{{.State.Running}}')) === 'true';
}

/**
 * Remove the Selenium container for a port, whether it runs or not.
 *
 * Docker's output is discarded, so a missing container is not reported.
 *
 * @param string $port
 *   The WebDriver port the container publishes.
 */
function selenium_container_remove(string $port): void {
  passthru(sprintf('docker rm -f %s >/dev/null 2>&1', escapeshellarg(selenium_container($port))));
}

// -----------------------------------------------------------------------------
// Drupal and Composer.
// -----------------------------------------------------------------------------

/**
 * Get extension information from the .info.yml file.
 *
 * Fails when no .info.yml file exists or it cannot be read.
 *
 * @return array{name: string, type: string}
 *   Array with 'name' (machine name) and 'type' ('module' or 'theme').
 */
function extension_info(): array {
  $info_files = glob('*.info.yml');

  if ($info_files === FALSE || $info_files === []) {
    FAIL('No .info.yml file found.');

    // @codeCoverageIgnoreStart
    return ['name' => '', 'type' => ''];
    // @codeCoverageIgnoreEnd
  }

  $name = basename($info_files[0], '.info.yml');
  $type = str_contains(file_read_or_fail($info_files[0]), 'type: theme') ? 'theme' : 'module';

  return ['name' => $name, 'type' => $type];
}

/**
 * Build the path to the extension's SQLite database file.
 *
 * @param string $extension_name
 *   Machine name of the extension, as returned by extension_info().
 *
 * @return string
 *   Absolute path to the SQLite database file used by the site-install
 *   and status commands.
 */
function site_db_file(string $extension_name): string {
  return '/tmp/site_' . $extension_name . '.sqlite';
}

/**
 * Get the install profile that the site is installed from.
 *
 * @return string
 *   DRUPAL_PROFILE from the environment or '.env', or 'standard'.
 */
function drupal_profile(): string {
  return resolve_env_value('DRUPAL_PROFILE', 'standard')['value'];
}

/**
 * Get the Drupal version a project builds when DRUPAL_VERSION is not set.
 *
 * @param string $dev_manifest
 *   Path to the 'composer.dev.json' file.
 *
 * @return string
 *   The 'extra.eddy.drupal-version' value of the file, or '11' when the file
 *   does not set one.
 */
function default_drupal_version(string $dev_manifest = 'composer.dev.json'): string {
  $version = json_file($dev_manifest)['extra']['eddy']['drupal-version'] ?? NULL;

  if (is_int($version)) {
    $version = (string) $version;
  }

  return is_string($version) && $version !== '' ? $version : '11';
}

/**
 * Run a drush command.
 *
 * @param string $command
 *   The drush command, optionally with sprintf-style placeholders.
 * @param string|string[]|null $args
 *   Arguments to substitute into the command. Each argument is escaped
 *   with escapeshellarg() before substitution.
 * @param int|null &$exit_code
 *   If provided, the exit code is stored here and failures do not cause
 *   the script to exit. If not provided, non-zero exit codes call fail().
 *
 * @param-out int $exit_code
 */
function drush(string $command, mixed $args = NULL, ?int &$exit_code = NULL): string {
  if (is_string($args)) {
    $args = [$args];
  }

  if (is_array($args) && $args !== []) {
    $command = sprintf($command, ...array_map(escapeshellarg(...), $args));
  }

  $exit_code_provided = $exit_code !== NULL;
  $exit_code = 0;

  $command = 'build/vendor/bin/drush -r ' . escapeshellarg(getcwd() . '/build/web') . ' -y ' . $command;

  if (is_debug()) {
    // Stream drush's progress - which it writes to stderr - live, while still
    // capturing stdout for the return value.
    ob_start();
    passthru($command, $exit_code);
    $output = ob_get_clean() ?: '';
  }
  else {
    // Fold stderr into the captured output so drush's progress notices stay
    // hidden during a normal run; the return value is still available to
    // callers that read it.
    $output = passthru_capture($command, $exit_code);
  }

  if (!$exit_code_provided && $exit_code !== 0) {
    // Surface the captured output so the failure is diagnosable. In debug mode
    // it holds only stdout, because stderr already streamed live.
    echo $output;

    FAIL('Drush command failed: %s.', $command);
  }

  return $output;
}

/**
 * Generate a one-time login link for the site.
 *
 * Drush 12 and 13 open the link in a browser unless 'uli' gets '--no-browser',
 * and the link works only once. Drush 14 rejects the option and never opens a
 * browser.
 *
 * @param string $site_url
 *   The site URL that the link starts with.
 *
 * @return string
 *   The link.
 */
function login_link(string $site_url): string {
  $options = (int) trim(drush('status --field=drush-version')) < 14 ? ' --no-browser' : '';

  return trim(drush('uli -l %s' . $options, [$site_url]));
}

/**
 * Redirect Drupal's browser test output directory into the logs directory.
 *
 * Drupal writes functional test HTML dumps - and, for JavaScript tests,
 * screenshots - to a location hardcoded to "<webroot>/sites/simpletest/
 * browser_output", ignoring any configured output directory
 * (https://www.drupal.org/node/2992069). Replacing that location with a
 * symlink into the logs directory gathers the output alongside the other
 * test artifacts instead of leaving it under the web root.
 *
 * Any existing file, directory, or symlink at the browser output location is
 * removed first. Does nothing when the web root is absent, and fails when a
 * directory or the symlink cannot be created.
 *
 * @param string $webroot
 *   Absolute path to the Drupal web root: the directory that contains "sites".
 * @param string $logs_dir
 *   Absolute path to the directory that gathers test artifacts.
 */
function link_browser_output(string $webroot, string $logs_dir): void {
  if (!is_dir($webroot)) {
    return;
  }

  $target = $logs_dir . '/browser_output';
  mkdir_or_fail($target);

  $simpletest_dir = $webroot . '/sites/simpletest';
  mkdir_or_fail($simpletest_dir);

  $link = $simpletest_dir . '/browser_output';

  if (is_link($link) || is_file($link)) {
    unlink($link);
  }
  elseif (is_dir($link)) {
    remove_dir($link);
  }

  symlink_or_fail($target, $link);
}

/**
 * Show a package from the Composer repositories, as decoded JSON.
 *
 * @param string $package
 *   The package name, such as 'drupal/core'.
 * @param string $version
 *   The version to show, or an empty string for the package with its list of
 *   versions.
 *
 * @return array<mixed>|null
 *   The decoded output, or NULL when Composer fails or prints no JSON object.
 */
function composer_show(string $package, string $version = ''): ?array {
  $output = [];
  $exit_code = 0;
  $command = 'composer show --all --format=json ' . escapeshellarg($package) . ($version === '' ? '' : ' ' . escapeshellarg($version));
  // exec() captures stdout only, so a Composer warning on stderr leaves the
  // JSON intact.
  exec($command, $output, $exit_code);

  $decoded = json_decode(implode("\n", $output), TRUE);

  return $exit_code === 0 && is_array($decoded) ? $decoded : NULL;
}

/**
 * Resolve a Drupal version constraint to the release it selects.
 *
 * Fails when the releases cannot be listed or none matches the constraint.
 *
 * @param string $constraint
 *   The constraint, such as '12', '11.1.0' or '12@beta'.
 *
 * @return string
 *   The selected release, such as '12.0.0-beta1'.
 *
 * @see drupal_release_select()
 */
function drupal_release(string $constraint): string {
  $versions = composer_show('drupal/recommended-project')['versions'] ?? NULL;

  if (!is_array($versions)) {
    FAIL('Unable to list the Drupal releases.');

    // @codeCoverageIgnoreStart
    return '';
    // @codeCoverageIgnoreEnd
  }

  $release = drupal_release_select($constraint, array_values(array_filter($versions, is_string(...))));

  if ($release === NULL) {
    FAIL('No Drupal release matches %s.', $constraint);

    // @codeCoverageIgnoreStart
    return '';
    // @codeCoverageIgnoreEnd
  }

  return $release;
}

/**
 * Select the newest Drupal release matching a version constraint.
 *
 * The major is fixed, and so is every number before the last: '12.1.0'
 * matches 12.1.x, while '12' and '12.1' match all of 12.x.
 *
 * Only stable releases qualify, or releases down to the stability flag in a
 * constraint such as '12@beta'. When none qualifies, the newest pre-release
 * is selected, so a version without a stable release still resolves.
 *
 * @param string $constraint
 *   The constraint, such as '12', '11.1.0' or '12@beta'.
 * @param array<int, string> $versions
 *   The available versions. Development branches, such as '12.0.x-dev', are
 *   skipped.
 *
 * @return string|null
 *   The selected release, or NULL when no release matches the constraint.
 */
function drupal_release_select(string $constraint, array $versions): ?string {
  if (preg_match('/^(\d+(?:\.\d+){0,2})(?:@(stable|rc|beta|alpha))?$/i', $constraint, $matches) !== 1) {
    return NULL;
  }

  // Keep the major, and every number before the last.
  $numbers = explode('.', $matches[1]);
  $prefix = implode('.', array_slice($numbers, 0, max(1, count($numbers) - 1))) . '.';

  $ranks = ['stable' => 0, 'rc' => 1, 'beta' => 2, 'alpha' => 3];
  $floor = $ranks[strtolower($matches[2] ?? 'stable')] ?? 0;

  $releases = [];

  foreach ($versions as $version) {
    if (str_starts_with($version, $prefix) && preg_match('/^\d+\.\d+\.\d+(?:-(rc|beta|alpha)\d+)?$/i', $version, $parts) === 1) {
      $releases[$version] = $ranks[strtolower($parts[1] ?? 'stable')] ?? 0;
    }
  }

  $qualified = array_keys(array_filter($releases, static fn(int $rank): bool => $rank <= $floor)) ?: array_keys($releases);

  $newest = NULL;

  foreach ($qualified as $release) {
    if ($newest === NULL || version_compare($release, $newest, '>')) {
      $newest = $release;
    }
  }

  return $newest;
}

/**
 * Get the PHP version constraint that a Drupal core release requires.
 *
 * @param string $release
 *   The release, such as '12.0.0-beta1'.
 *
 * @return string|null
 *   The constraint, such as '>=8.5', or NULL when it cannot be read.
 */
function drupal_php_requirement(string $release): ?string {
  $requirement = composer_show('drupal/core', $release)['requires']['php'] ?? NULL;

  return is_string($requirement) && $requirement !== '' ? $requirement : NULL;
}

/**
 * Get the PHP version that Composer manifests pin with 'config.platform.php'.
 *
 * @param string ...$manifests
 *   The manifest paths, in order of precedence.
 *
 * @return string|null
 *   The version from the first manifest that sets one, or NULL when none
 *   does.
 */
function config_platform_php(string ...$manifests): ?string {
  foreach ($manifests as $manifest) {
    $php = json_file($manifest)['config']['platform']['php'] ?? NULL;

    if (is_string($php) && $php !== '') {
      return $php;
    }
  }

  return NULL;
}

/**
 * Check whether a version satisfies a Composer version constraint.
 *
 * Handles comparisons such as '>=8.3.0', alone or combined: with a space or a
 * comma for AND, and with '||' for OR. A version number with fewer parts is
 * padded with zeros, so '>=8.5' accepts '8.5.0'.
 *
 * @param string $version
 *   The version, such as '8.4.17'.
 * @param string $constraint
 *   The constraint, such as '>=8.5' or '>=8.3 <9 || >=10'.
 *
 * @return bool|null
 *   Whether the version satisfies the constraint, or NULL when the constraint
 *   holds another form, such as '^8.3'.
 */
function version_satisfies(string $version, string $constraint): ?bool {
  $pad = static fn(string $number): string => implode('.', array_pad(explode('.', $number), 4, '0'));
  // An operator may stand apart from its version, as in '>= 8.3'.
  $constraint = preg_replace('/(<>|!=|>=?|<=?|==?)\s+/', '$1', trim($constraint)) ?? '';
  $satisfied = FALSE;

  foreach (preg_split('/\s*\|\|?\s*/', $constraint) ?: [] as $alternative) {
    $all = TRUE;

    foreach (preg_split('/\s*,\s*|\s+/', $alternative) ?: [] as $comparison) {
      if (preg_match('/^(<>|!=|>=|<=|>|<|==|=)?v?(\d+(?:\.\d+){0,3})$/', $comparison, $matches) !== 1) {
        return NULL;
      }

      $operator = match ($matches[1]) {
        '<>', '!=' => '!=',
        '>=' => '>=',
        '<=' => '<=',
        '>' => '>',
        '<' => '<',
        default => '==',
      };
      $all = $all && version_compare($pad($version), $pad($matches[2]), $operator);
    }

    $satisfied = $satisfied || $all;
  }

  return $satisfied;
}

/**
 * Read a field of each package that a Composer lock file installs.
 *
 * @param string $lock_file
 *   The path to the lock file.
 * @param string $field
 *   The field of a package entry, such as 'version' or 'type'.
 *
 * @return array<string, string>
 *   The field values keyed by package name, from both 'packages' and
 *   'packages-dev'. A package without a string value for the field is left
 *   out. Empty when the lock file cannot be read.
 */
function locked_packages(string $lock_file, string $field): array {
  $lock = json_file($lock_file);
  $packages = array_merge((array) ($lock['packages'] ?? []), (array) ($lock['packages-dev'] ?? []));

  /** @var array<string, string> $values */
  $values = array_filter(array_column($packages, $field, 'name'), is_string(...));

  return $values;
}

/**
 * List the packages a Composer lock file installs from development branches.
 *
 * @param string $lock_file
 *   The path to the lock file.
 *
 * @return array<string, string>
 *   The branch versions keyed by package name, such as '14.x-dev' or
 *   'dev-main'.
 */
function dev_branch_packages(string $lock_file): array {
  return array_filter(locked_packages($lock_file, 'version'), static fn(string $version): bool => str_starts_with($version, 'dev-') || str_ends_with($version, '-dev'));
}

// -----------------------------------------------------------------------------
// Node.js.
// -----------------------------------------------------------------------------

/**
 * Get the directory that nvm is installed in.
 *
 * @return string
 *   NVM_DIR from the environment or '.env', or the directory that the nvm
 *   installer defaults to: '$XDG_CONFIG_HOME/nvm', or '$HOME/.nvm' when
 *   XDG_CONFIG_HOME is not set.
 *
 * @see https://github.com/nvm-sh/nvm#installing-and-updating
 */
function nvm_dir(): string {
  $nvm_dir = resolve_env_value('NVM_DIR', '')['value'];

  if ($nvm_dir !== '') {
    return $nvm_dir;
  }

  $config_home = getenv_default('XDG_CONFIG_HOME', '');

  return $config_home === '' ? getenv_default('HOME', '') . '/.nvm' : $config_home . '/nvm';
}

/**
 * Get the prefix that runs a command on the Node.js version of '.nvmrc'.
 *
 * 'nvm' is a shell function that 'nvm.sh' defines, so the prefix loads
 * 'nvm.sh' into the shell that runs the command. 'nvm install' fetches the
 * version when nvm does not have it.
 *
 * Prints a note that reports whether nvm is used, and exports NVM_DIR when it
 * is.
 *
 * @return string
 *   The prefix, or an empty string when the working directory has no '.nvmrc'
 *   or nvm is not installed.
 */
function nvm_prefix(): string {
  if (!is_file('.nvmrc')) {
    return '';
  }

  $nvm_dir = nvm_dir();

  if (!is_file($nvm_dir . '/nvm.sh')) {
    NOTE('nvm is not installed in %s; using the Node.js on PATH, not the version in .nvmrc.', $nvm_dir);

    return '';
  }

  NOTE('Using the Node.js version in .nvmrc with nvm from %s.', $nvm_dir);

  // NVM_DIR may be unset when nvm is in its default directory.
  putenv('NVM_DIR=' . $nvm_dir);

  // Loading 'nvm.sh' runs a silent 'nvm use' that can fail, so its status is
  // ignored.
  return '. "$NVM_DIR/nvm.sh"; { nvm use || nvm install; } && ';
}

// -----------------------------------------------------------------------------
// Cloudflare quick tunnel.
// -----------------------------------------------------------------------------

/**
 * Check whether the Cloudflare quick tunnel is enabled.
 *
 * Resolves CLOUDFLARE_TUNNEL via the env -> '.env' chain. An empty value and
 * '0', 'false', 'no' and 'off', in any letter case, disable the tunnel.
 *
 * @return bool
 *   TRUE when the tunnel is enabled.
 */
function tunnel_enabled(): bool {
  $value = strtolower(trim(resolve_env_value('CLOUDFLARE_TUNNEL', '')['value']));

  return !in_array($value, ['', '0', 'false', 'no', 'off'], TRUE);
}

// 'cloudflared' logs 'https://api.trycloudflare.com' when the request that
// creates a quick tunnel fails, so that host is excluded.
const TUNNEL_URL_PATTERN = 'https://(?!api\.)[a-z0-9-]+\.trycloudflare\.com';

const TUNNEL_PID_FILE = LOGS_DIR . '/cloudflared.pid';

const TUNNEL_LOG_FILE = LOGS_DIR . '/cloudflared.log';

/**
 * Expose the webserver through a Cloudflare quick tunnel.
 *
 * A running tunnel is reused while its URL responds. Otherwise a new tunnel
 * is started and its public URL is written to '.env' as TUNNEL_URL. A missing
 * 'cloudflared' binary, a log or PID file that cannot be written, a launch
 * without a PID or a tunnel that publishes no URL is reported without
 * failing, so the webserver stays available locally.
 *
 * @param string $port
 *   The webserver port the tunnel forwards to.
 */
function tunnel_start(string $port): void {
  TASK('Starting the Cloudflare quick tunnel.');

  $pid = tunnel_pid();

  if ($pid !== NULL) {
    $url = tunnel_log_url();

    // A quick tunnel can lose its edge connection while 'cloudflared' keeps
    // running, so a live process does not prove a reachable URL.
    if ($url !== '' && tunnel_forwards_to($pid, $port) && tunnel_responds($url)) {
      dotenv_write_var('TUNNEL_URL', $url);
      PASS('Reusing the tunnel at %s.', $url);

      return;
    }

    NOTE('The running tunnel cannot be reused; stopping it.');
    tunnel_kill($pid);
  }

  if (command_path('cloudflared') === FALSE) {
    // A previous tunnel's URL would otherwise be reported as the site URL.
    tunnel_forget();
    NOTE('cloudflared is not on PATH; skipping the tunnel.');

    return;
  }

  mkdir_or_fail(dirname(TUNNEL_LOG_FILE));

  // Empty the log before the new process starts, so the URL of the previous
  // tunnel cannot be read back as the new one.
  if (file_put_contents(TUNNEL_LOG_FILE, '') === FALSE) {
    tunnel_forget();
    NOTE('Unable to write %s. Continuing without the tunnel.', TUNNEL_LOG_FILE);

    return;
  }

  $pid = (int) trim((string) shell_exec(sprintf('nohup cloudflared tunnel --url %s --no-autoupdate >%s 2>&1 & echo $!', escapeshellarg('http://localhost:' . $port), escapeshellarg(TUNNEL_LOG_FILE))));

  if ($pid < 1) {
    tunnel_forget();
    NOTE('Unable to read the cloudflared PID; see %s. Continuing without the tunnel.', TUNNEL_LOG_FILE);

    return;
  }

  if (file_put_contents(TUNNEL_PID_FILE, $pid . PHP_EOL) === FALSE) {
    // tunnel_stop() finds the tunnel only through its PID file, so a tunnel
    // without one would stay public after 'eddy-stop'.
    tunnel_kill($pid);
    tunnel_forget();
    NOTE('Unable to write %s. Stopped the tunnel and continuing without it.', TUNNEL_PID_FILE);

    return;
  }

  NOTE('Waiting for the tunnel URL.');
  $url = '';

  for ($i = 0; $i < 30; $i++) {
    $url = tunnel_log_url();

    if ($url !== '') {
      break;
    }

    sleep(1);
  }

  if ($url === '') {
    tunnel_kill($pid);
    tunnel_forget();
    NOTE('The tunnel published no URL; see %s. Continuing without the tunnel.', TUNNEL_LOG_FILE);

    return;
  }

  dotenv_write_var('TUNNEL_URL', $url);
  PASS('Tunnel started at %s.', $url);
}

/**
 * Stop the Cloudflare quick tunnel and forget its URL.
 *
 * Runs whether or not the tunnel is enabled, so a tunnel started before it
 * was disabled is still stopped.
 */
function tunnel_stop(): void {
  $pid = tunnel_pid();

  if ($pid !== NULL) {
    TASK('Stopping the Cloudflare quick tunnel.');
    tunnel_kill($pid);

    // 'cloudflared' drains open requests before it exits, so wait up to 3
    // seconds for it.
    for ($i = 0; $i < 3; $i++) {
      $exit_code = 0;
      passthru(sprintf('kill -0 %d >/dev/null 2>&1', $pid), $exit_code);

      if ($exit_code !== 0) {
        break;
      }

      sleep(1);
    }

    PASS('Tunnel stopped.');
  }

  tunnel_forget();
}

/**
 * Add the settings Drupal needs to serve the site through the tunnel.
 *
 * The reverse-proxy settings make Drupal trust the X-Forwarded-* headers that
 * 'cloudflared' sends from the local host, so it detects HTTPS. The
 * trusted-host patterns accept the '*.trycloudflare.com' host and the local
 * webserver hosts, because Drupal rejects every host that no pattern matches
 * once any pattern is set. The settings are appended to 'settings.php' once.
 *
 * @param string $settings_file
 *   The path to the site's 'settings.php'.
 */
function tunnel_write_settings(string $settings_file): void {
  if (!is_file($settings_file)) {
    NOTE('%s not found; skipping the tunnel settings.', $settings_file);

    return;
  }

  $marker = '# Cloudflare quick tunnel settings.';

  if (str_contains(file_read_or_fail($settings_file), $marker)) {
    return;
  }

  TASK('Adding the tunnel settings to %s.', $settings_file);

  $settings = <<<'PHP'
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = ['127.0.0.1', '::1'];
$settings['trusted_host_patterns'][] = '^[a-z0-9-]+\.trycloudflare\.com$';
$settings['trusted_host_patterns'][] = '^localhost$';
$settings['trusted_host_patterns'][] = '^127\.0\.0\.1$';
$settings['trusted_host_patterns'][] = '^0\.0\.0\.0$';
PHP;

  // The installer leaves 'settings.php' read-only.
  $mode = fileperms($settings_file) ?: 0644;
  chmod($settings_file, ($mode & 0777) | 0200);

  file_write_or_fail($settings_file, PHP_EOL . $marker . PHP_EOL . $settings . PHP_EOL, FILE_APPEND);

  PASS('Tunnel settings added.');
}

/**
 * Get the PID of the running Cloudflare quick tunnel.
 *
 * @return int|null
 *   The PID from the PID file while it belongs to a running 'cloudflared'
 *   process, or NULL. The process check keeps a recycled PID from being
 *   signalled.
 */
function tunnel_pid(): ?int {
  $pid = trim(file_read(TUNNEL_PID_FILE));

  if (!is_positive_integer($pid)) {
    return NULL;
  }

  return str_contains(process_command((int) $pid), 'cloudflared') ? (int) $pid : NULL;
}

/**
 * Check whether a tunnel process forwards to a webserver port.
 *
 * @param int $pid
 *   The tunnel process ID.
 * @param string $port
 *   The webserver port.
 *
 * @return bool
 *   TRUE when the process was started with '--url http://localhost:<port>'.
 */
function tunnel_forwards_to(int $pid, string $port): bool {
  return preg_match('#--url http://localhost:' . preg_quote($port, '#') . '(?:\s|$)#', process_command($pid)) === 1;
}

/**
 * Get the public URL that the tunnel wrote to its log.
 *
 * @return string
 *   The quick tunnel URL, or an empty string while the log holds none.
 */
function tunnel_log_url(): string {
  $log = file_read(TUNNEL_LOG_FILE);

  return preg_match('#' . TUNNEL_URL_PATTERN . '#', $log, $matches) === 1 ? $matches[0] : '';
}

/**
 * Check whether a tunnel URL answers.
 *
 * @param string $url
 *   The tunnel URL.
 *
 * @return bool
 *   TRUE when the URL answers with a status below 400. Redirects are not
 *   followed.
 */
function tunnel_responds(string $url): bool {
  $options = ['method' => 'HEAD', 'timeout' => 5, 'ignore_errors' => TRUE, 'follow_location' => 0];
  $headers = @get_headers($url, FALSE, stream_context_create(['http' => $options]));

  return is_array($headers) && preg_match('#^HTTP/\S+\s+[1-3]\d\d\b#', $headers[0] ?? '') === 1;
}

/**
 * Terminate a tunnel process with 'kill'.
 *
 * A process ID below 1 is ignored, because 'kill' signals a whole process
 * group for 0 and negative IDs.
 *
 * @param int $pid
 *   The process ID.
 */
function tunnel_kill(int $pid): void {
  if ($pid < 1) {
    return;
  }

  passthru(sprintf('kill %d >/dev/null 2>&1', $pid));
}

/**
 * Remove the tunnel's PID file and its URL from '.env'.
 *
 * A TUNNEL_URL that another tunnel tool wrote is kept.
 */
function tunnel_forget(): void {
  if (is_file(TUNNEL_PID_FILE)) {
    unlink(TUNNEL_PID_FILE);
  }

  if (preg_match('#^' . TUNNEL_URL_PATTERN . '$#', dotenv_read()['TUNNEL_URL'] ?? '') === 1) {
    dotenv_unset_var('TUNNEL_URL');
  }
}

// Never run the real quit() function during tests. This also avoids bleeding
// into global namespace when running multiple tests that share the same
// test process.
// Note that this replicates the behaviour of global built-in functions
// like passthru() and exec() which are *not defined in this namespace*. We only
// defined quit() in a namespace because mocking of global functions can only
// be done if they are defined in a namespace.
// @codeCoverageIgnoreStart
if (!function_exists('DrevOps\Eddy\Tooling\quit') && !class_exists('PHPUnit\\Framework\\TestCase')) {

  /**
   * Exit script with given code.
   *
   * Wrapper around exit() to allow mocking in tests since exit() cannot be
   * directly mocked despite being a function in PHP 8.4+.
   *
   * @param int $exit_code
   *   Exit code (0 for success, non-zero for error).
   */
  function quit(int $exit_code = 0): void {
    exit($exit_code);
  }

}
// @codeCoverageIgnoreEnd
