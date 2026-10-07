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

namespace DrevOps\Eddy\DevTools;

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
 *   Associative array of key-value pairs. Empty if the file does not exist
 *   or contains no parseable lines.
 */
function dotenv_read(string $dotenv_file = '.env'): array {
  if (!file_exists($dotenv_file)) {
    return [];
  }

  $contents = file_get_contents($dotenv_file);
  if ($contents === FALSE) {
    return [];
  }

  $vars = [];
  foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
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

  if (!file_exists($dotenv_file)) {
    if (file_put_contents($dotenv_file, $assignment . PHP_EOL) === FALSE) {
      FAIL('Unable to write %s', $dotenv_file);
    }

    return;
  }

  $contents = file_get_contents($dotenv_file);
  if ($contents === FALSE) {
    FAIL('Unable to read %s', $dotenv_file);

    // @codeCoverageIgnoreStart
    return;
    // @codeCoverageIgnoreEnd
  }

  $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
  $trailing_newline = $contents !== '' && (str_ends_with($contents, "\n") || str_ends_with($contents, "\r"));
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

  if (file_put_contents($dotenv_file, implode(PHP_EOL, $lines) . PHP_EOL) === FALSE) {
    FAIL('Unable to write %s', $dotenv_file);
  }
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
  $contents = file_exists($dotenv_file) ? (string) file_get_contents($dotenv_file) : '';
  $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
  $kept = preg_grep('/^\s*' . preg_quote($key, '/') . '\s*=/', $lines, PREG_GREP_INVERT) ?: [];

  if (count($kept) === count($lines)) {
    return;
  }

  if (file_put_contents($dotenv_file, implode(PHP_EOL, $kept)) === FALSE) {
    FAIL('Unable to write %s', $dotenv_file);
  }
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
 * Detect the XDebug state of the dev server listening on the given port.
 *
 * Inspects the running PHP process's command line for the
 * 'xdebug.mode=debug' flag. Returns 'enabled' or 'disabled' when a
 * server is listening, and '-' when no server is bound to the port.
 */
function xdebug_state(string $port): string {
  $command = sprintf('ps -o command= -p "$(lsof -ti:%s 2>/dev/null | head -1)" 2>/dev/null', escapeshellarg($port));
  $out = trim((string) @shell_exec($command));
  if ($out === '') {
    return '-';
  }

  return str_contains($out, 'xdebug.mode=debug') ? 'enabled' : 'disabled';
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
  if (!ctype_digit($value) || (int) $value < 1 || (int) $value > 65535) {
    FAIL('Invalid %s "%s". Expected integer in range 1-65535.', $source, $value);
  }
}

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
    FAIL('Start port must be between 1 and 65535, got %d', $start);
  }
  if ($max_attempts < 1) {
    FAIL('Max attempts must be a positive integer, got %d', $max_attempts);
  }

  for ($port = $start; $port < $start + $max_attempts; $port++) {
    $conn = @stream_socket_client(sprintf('tcp://localhost:%d', $port), $errno, $errstr, 0.2);
    if ($conn === FALSE) {
      return $port;
    }
    fclose($conn);
  }

  FAIL('Unable to find a free port in range %d-%d', $start, $start + $max_attempts - 1);

  // @codeCoverageIgnoreStart
  return $start;
  // @codeCoverageIgnoreEnd
}

/**
 * Kill whatever process is listening on a TCP port, if any.
 *
 * A best-effort operation: a missing listener or a missing 'lsof' binary
 * are silenced so callers may invoke it unconditionally before (re)binding
 * the same port.
 *
 * @param int|string $port
 *   The TCP port to free.
 */
function kill_port(int|string $port): void {
  @passthru(sprintf('lsof -ti:%s 2>/dev/null | xargs kill -9 2>/dev/null', escapeshellarg((string) $port)));
}

/**
 * Get required environment variable with fallback support.
 */
function getenv_required(mixed ...$vars): string {
  if (count($vars) < 1) {
    throw new \InvalidArgumentException('getenv_required() requires at least 1 argument');
  }

  $vars[] = '';

  $value = getenv_default(...$vars);

  if ($value !== '') {
    return $value;
  }

  FAIL('Missing required value for %s', implode(', ', array_filter(array_map(static fn(mixed $v): string => is_string($v) ? $v : '', $vars))));

  // @codeCoverageIgnoreStart
  return '';
  // @codeCoverageIgnoreEnd
}

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
  echo term_supports_color() ?
    "\033[34m[TASK] " . sprintf($format, ...$args) . "\033[0m\n" :
    sprintf('[TASK] %s%s', sprintf($format, ...$args), PHP_EOL);
}

/**
 * Output an info message.
 */
function INFO(string $format, string|int|float ...$args): void {
  echo term_supports_color() ?
    "\033[36m[INFO] " . sprintf($format, ...$args) . "\033[0m\n" :
    sprintf('[INFO] %s%s', sprintf($format, ...$args), PHP_EOL);
}

/**
 * Output a success message.
 */
function PASS(string $format, string|int|float ...$args): void {
  echo term_supports_color() ?
    "\033[32m[ OK ] " . sprintf($format, ...$args) . "\033[0m\n" :
    sprintf('[ OK ] %s%s', sprintf($format, ...$args), PHP_EOL);
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
  echo term_supports_color() ?
    "\033[31m[FAIL] " . sprintf($format, ...$args) . "\033[0m\n" :
    sprintf('[FAIL] %s%s', sprintf($format, ...$args), PHP_EOL);
}

/**
 * Check if terminal supports colors.
 */
function term_supports_color(): bool {
  return getenv('TERM') === 'dumb' || getenv('TERM') === FALSE ? FALSE : function_exists('posix_isatty') && @posix_isatty(STDOUT);
}

/**
 * Get the path to a command, or FALSE if the command does not exist.
 */
function command_path(string $command): string|false {
  if (!preg_match('/^[A-Za-z0-9_\-]+(?: [A-Za-z0-9_\-]+)*$/', $command)) {
    return FALSE;
  }
  exec(sprintf('command -v %s 2>/dev/null', $command), $output, $code);
  return $code === 0 && !empty($output[0]) ? trim($output[0]) : FALSE;
}

/**
 * Require a command to be available, or fail.
 */
function command_must_exist(string $command): void {
  if (!command_path($command)) {
    FAIL("Command '%s' is not available", $command);
  }
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

  if (!command_path('qrencode')) {
    FAIL("Command 'qrencode' is not available. Install it from https://fukuchi.org/works/qrencode/ to render QR codes");
  }

  passthru(sprintf('qrencode -t ANSIUTF8 %s', escapeshellarg($url)));
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

    FAIL('Drush command failed: %s', $command);
  }

  return $output;
}

/**
 * Get extension information from the .info.yml file.
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
  $type = str_contains((string) file_get_contents($info_files[0]), 'type: theme') ? 'theme' : 'module';

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
  $config = json_decode((string) @file_get_contents($dev_manifest), TRUE);
  $version = is_array($config) ? ($config['extra']['eddy']['drupal-version'] ?? NULL) : NULL;

  if (is_int($version)) {
    $version = (string) $version;
  }

  return is_string($version) && $version !== '' ? $version : '11';
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
  $output = [];
  $exit_code = 0;
  // exec() captures stdout only, so a Composer warning on stderr leaves the
  // JSON intact.
  exec('composer show --all --format=json drupal/recommended-project', $output, $exit_code);

  $package = json_decode(implode("\n", $output), TRUE);
  if ($exit_code !== 0 || !is_array($package) || !isset($package['versions']) || !is_array($package['versions'])) {
    FAIL('Unable to list the Drupal releases.');

    // @codeCoverageIgnoreStart
    return '';
    // @codeCoverageIgnoreEnd
  }

  $release = drupal_release_select($constraint, array_values(array_filter($package['versions'], is_string(...))));
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
  $lock = json_decode((string) @file_get_contents($lock_file), TRUE);
  if (!is_array($lock)) {
    return [];
  }

  /** @var array<string, string> $versions */
  $versions = array_column(array_merge((array) ($lock['packages'] ?? []), (array) ($lock['packages-dev'] ?? [])), 'version', 'name');

  return array_filter($versions, static fn(string $version): bool => str_starts_with($version, 'dev-') || str_ends_with($version, '-dev'));
}

/**
 * Check if debug mode is enabled.
 */
function is_debug(): bool {
  return getenv('DEBUG') === '1';
}

/**
 * Replace content in a file using a regular expression.
 *
 * @return string
 *   The file content after replacement.
 */
function replace_in_file(string $file, string $pattern, string $replacement): string {
  $content = file_get_contents($file);
  if ($content === FALSE) {
    FAIL('Unable to read file %s.', $file);

    // @codeCoverageIgnoreStart
    return '';
    // @codeCoverageIgnoreEnd
  }

  $replaced = preg_replace($pattern, $replacement, $content);
  if ($replaced === NULL) {
    FAIL('Regex replacement failed in file %s with pattern %s.', $file, $pattern);

    // @codeCoverageIgnoreStart
    return $content;
    // @codeCoverageIgnoreEnd
  }

  file_put_contents($file, $replaced);

  return $replaced;
}

/**
 * Recursively remove a directory.
 */
function remove_dir(string $dir): void {
  if (!is_dir($dir)) {
    return;
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
  @rmdir($dir);
}

/**
 * Recursively copy a directory.
 */
function copy_dir(string $src, string $dst): void {
  if (!is_dir($dst)) {
    mkdir($dst, 0755, TRUE);
  }
  $iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
    \RecursiveIteratorIterator::SELF_FIRST
  );
  /** @var \RecursiveDirectoryIterator $item */
  foreach ($iterator as $item) {
    $target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
    if ($item->isDir()) {
      if (!is_dir($target)) {
        mkdir($target, 0755, TRUE);
      }
    }
    else {
      copy($item->getPathname(), $target);
    }
  }
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
 * removed first. Does nothing when the web root is absent.
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
  if (!is_dir($target)) {
    mkdir($target, 0755, TRUE);
  }

  $simpletest_dir = $webroot . '/sites/simpletest';
  if (!is_dir($simpletest_dir)) {
    mkdir($simpletest_dir, 0755, TRUE);
  }

  $link = $simpletest_dir . '/browser_output';
  if (is_link($link) || is_file($link)) {
    unlink($link);
  }
  elseif (is_dir($link)) {
    remove_dir($link);
  }

  symlink($target, $link);
}

const TUNNEL_PID_FILE = '.logs/cloudflared.pid';

const TUNNEL_LOG_FILE = '.logs/cloudflared.log';

// 'cloudflared' logs 'https://api.trycloudflare.com' when the request that
// creates a quick tunnel fails, so that host is excluded.
const TUNNEL_URL_PATTERN = 'https://(?!api\.)[a-z0-9-]+\.trycloudflare\.com';

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

/**
 * Expose the webserver through a Cloudflare quick tunnel.
 *
 * A running tunnel is reused while its URL responds. Otherwise a new tunnel
 * is started and its public URL is written to '.env' as TUNNEL_URL. A missing
 * 'cloudflared' binary or a tunnel that publishes no URL is reported without
 * failing, so the webserver stays available locally.
 *
 * @param string $port
 *   The webserver port the tunnel forwards to.
 */
function tunnel_start(string $port): void {
  TASK('Starting the Cloudflare quick tunnel.');

  if (command_path('cloudflared') === FALSE) {
    NOTE('cloudflared is not on PATH; skipping the tunnel.');

    return;
  }

  $pid = tunnel_pid();

  if ($pid !== NULL) {
    $url = tunnel_log_url();

    // A quick tunnel can lose its edge connection while 'cloudflared' keeps
    // running, so a live process does not prove a reachable URL.
    if ($url !== '' && tunnel_responds($url)) {
      dotenv_write_var('TUNNEL_URL', $url);
      PASS('Reusing the tunnel at %s.', $url);

      return;
    }

    NOTE('The running tunnel does not respond; restarting it.');
    tunnel_kill($pid);
  }

  if (!is_dir(dirname(TUNNEL_LOG_FILE))) {
    mkdir(dirname(TUNNEL_LOG_FILE), 0755, TRUE);
  }

  // Empty the log before the new process starts, so the URL of the previous
  // tunnel cannot be read back as the new one.
  file_put_contents(TUNNEL_LOG_FILE, '');

  $pid = (int) trim((string) shell_exec(sprintf('nohup cloudflared tunnel --url %s --no-autoupdate >%s 2>&1 & echo $!', escapeshellarg('http://localhost:' . $port), escapeshellarg(TUNNEL_LOG_FILE))));
  file_put_contents(TUNNEL_PID_FILE, $pid . PHP_EOL);

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

  if (str_contains((string) file_get_contents($settings_file), $marker)) {
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

  if (file_put_contents($settings_file, PHP_EOL . $marker . PHP_EOL . $settings . PHP_EOL, FILE_APPEND) === FALSE) {
    FAIL('Unable to write %s.', $settings_file);
  }

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
  $pid = is_file(TUNNEL_PID_FILE) ? trim((string) file_get_contents(TUNNEL_PID_FILE)) : '';

  if (!ctype_digit($pid) || (int) $pid < 1) {
    return NULL;
  }

  $command = (string) shell_exec(sprintf('ps -p %d -o command= 2>/dev/null', (int) $pid));

  return str_contains($command, 'cloudflared') ? (int) $pid : NULL;
}

/**
 * Get the public URL that the tunnel wrote to its log.
 *
 * @return string
 *   The quick tunnel URL, or an empty string while the log holds none.
 */
function tunnel_log_url(): string {
  $log = is_file(TUNNEL_LOG_FILE) ? (string) file_get_contents(TUNNEL_LOG_FILE) : '';

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
  $context = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 5, 'ignore_errors' => TRUE, 'follow_location' => 0]]);
  $headers = @get_headers($url, FALSE, $context);

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
if (!function_exists('DrevOps\Eddy\DevTools\quit') && !class_exists('PHPUnit\\Framework\\TestCase')) {

  /**
   * Exit script with given code.
   *
   * Wrapper around exit() to allow mocking in tests since exit() cannot be
   * directly mocked despite being a function in PHP 8.4+.
   *
   * @param int $code
   *   Exit code (0 for success, non-zero for error).
   */
  function quit(int $code = 0): void {
    exit($code);
  }

}
// @codeCoverageIgnoreEnd
