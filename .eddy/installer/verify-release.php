#!/usr/bin/env php
<?php

/**
 * @file
 * Verify the signed checksums of the Eddy release assets in a directory.
 *
 * Passes when 'SHA256SUMS.asc' is a valid signature of 'SHA256SUMS' by the
 * Eddy signing key, and every file that 'SHA256SUMS' lists is in the
 * directory and matches its checksum. The key comes from 'install-key.asc'
 * in the repository root.
 *
 * Dependencies: gpg, gpgv
 *
 * Usage:
 * @code
 * php .eddy/installer/verify-release.php <directory>
 * @endcode
 */

declare(strict_types=1);

namespace DrevOps\Eddy\Installer;

/**
 * The fingerprint of the primary key of the Eddy signing key.
 *
 * 'gpg --verify' accepts a signature by any key in the keyring, so the
 * signature must come from this key.
 */
const SIGNING_KEY_FINGERPRINT = '7B8D3C9793E0FF52EAABFEC8E5322FD0EC15511E';

/**
 * The public half of the Eddy signing key.
 */
const PUBLIC_KEY = __DIR__ . '/../../install-key.asc';

/**
 * Verify the release assets in the directory passed as the first argument.
 *
 * @param array<int, string> $argv
 *   The command line arguments.
 * @param string $public_key
 *   The path to the armored public key of the signing key.
 * @param string $fingerprint
 *   The fingerprint of the primary key that must have made the signature.
 *
 * @return int
 *   The exit code.
 */
function main(array $argv, string $public_key = PUBLIC_KEY, string $fingerprint = SIGNING_KEY_FINGERPRINT): int {
  try {
    verify_release($argv[1] ?? '', $public_key, $fingerprint);
  }
  catch (\RuntimeException $runtime_exception) {
    echo 'Error: ' . $runtime_exception->getMessage() . PHP_EOL;

    return 1;
  }

  return 0;
}

/**
 * Verify the signature of 'SHA256SUMS' and the files it lists.
 *
 * @param string $dir
 *   The directory that holds 'SHA256SUMS', 'SHA256SUMS.asc' and the files.
 * @param string $public_key
 *   The path to the armored public key of the signing key.
 * @param string $fingerprint
 *   The fingerprint of the primary key that must have made the signature.
 *
 * @throws \RuntimeException
 *   When a file is missing, the signature is not by the key, or a file does
 *   not match its checksum.
 */
function verify_release(string $dir, string $public_key, string $fingerprint): void {
  if ($dir === '' || !is_dir($dir)) {
    throw new \RuntimeException('Pass the directory that holds SHA256SUMS and SHA256SUMS.asc.');
  }

  foreach (['SHA256SUMS', 'SHA256SUMS.asc', $public_key] as $file) {
    $path = $file === $public_key ? $file : $dir . '/' . $file;

    if (!is_file($path)) {
      throw new \RuntimeException(sprintf('%s does not exist.', $path));
    }
  }

  // 'gpgv' checks the signature against a keyring that holds only the public
  // key, with no keyring of the user and no 'gpg-agent'. It reads a binary
  // keyring from a file, which a path with a slash names outside its home.
  $keyring = realpath($dir) . '/.verify-release-keyring.gpg';

  try {
    [$dearmor_code, , $dearmor_error] = run(['gpg', '--batch', '--yes', '--output', $keyring, '--dearmor', $public_key]);

    if ($dearmor_code !== 0) {
      throw new \RuntimeException(sprintf('gpg could not read the public key %s: %s', $public_key, trim($dearmor_error)));
    }

    // Only the status lines on the standard output are parsed, because the
    // standard error holds text that the keys supply, such as user IDs.
    $sums = $dir . '/SHA256SUMS';
    [, $status] = run(['gpgv', '--status-fd', '1', '--keyring', $keyring, $sums . '.asc', $sums]);
  }
  finally {
    if (is_file($keyring)) {
      unlink($keyring);
    }
  }

  if (!is_signed_by($status, $fingerprint)) {
    throw new \RuntimeException(sprintf('%s/SHA256SUMS is not signed by the Eddy signing key %s.', $dir, $fingerprint));
  }

  echo sprintf('SHA256SUMS is signed by the Eddy signing key %s.', $fingerprint) . PHP_EOL;

  foreach (verify_checksums($dir) as $file) {
    echo sprintf('%s: OK', $file) . PHP_EOL;
  }
}

/**
 * Check whether the status output of 'gpg --verify' holds a valid signature.
 *
 * A 'VALIDSIG' line names the key that made the signature, then the primary
 * key of that key last, so a signature by a subkey passes too.
 *
 * @param string $status
 *   The output of 'gpg --status-fd 1 --verify'.
 * @param string $fingerprint
 *   The fingerprint of the primary key.
 *
 * @return bool
 *   TRUE when a valid signature names the fingerprint.
 */
function is_signed_by(string $status, string $fingerprint): bool {
  foreach (explode("\n", $status) as $line) {
    $fields = explode(' ', trim($line));

    if (count($fields) < 3 || $fields[0] !== '[GNUPG:]' || $fields[1] !== 'VALIDSIG') {
      continue;
    }

    if ($fields[2] === $fingerprint || end($fields) === $fingerprint) {
      return TRUE;
    }
  }

  return FALSE;
}

/**
 * Verify every file that 'SHA256SUMS' in a directory lists.
 *
 * @param string $dir
 *   The directory that holds 'SHA256SUMS' and the files.
 *
 * @return array<int, string>
 *   The names of the verified files.
 *
 * @throws \RuntimeException
 *   When a line is malformed, a file is missing or does not match.
 */
function verify_checksums(string $dir): array {
  $verified = [];

  foreach (explode("\n", trim((string) file_get_contents($dir . '/SHA256SUMS'))) as $line) {
    // A name with a slash could point outside the directory.
    if (!preg_match('/^([0-9a-f]{64}) [ *]([^\/]+)$/', $line, $matches) || in_array($matches[2], ['.', '..'], TRUE)) {
      throw new \RuntimeException(sprintf('SHA256SUMS holds a line that is not a checksum of a file in the directory: "%s".', $line));
    }

    [, $checksum, $file] = $matches;

    if (!is_file($dir . '/' . $file)) {
      throw new \RuntimeException(sprintf('%s/%s, listed in SHA256SUMS, does not exist.', $dir, $file));
    }

    if (hash_file('sha256', $dir . '/' . $file) !== $checksum) {
      throw new \RuntimeException(sprintf('%s/%s does not match its checksum in SHA256SUMS.', $dir, $file));
    }

    $verified[] = $file;
  }

  return $verified;
}

/**
 * Run a command.
 *
 * @param list<string> $command
 *   The command and its arguments.
 *
 * @return array{0: int, 1: string, 2: string}
 *   The exit code, the standard output and the standard error.
 */
function run(array $command): array {
  $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

  // @codeCoverageIgnoreStart
  if (!is_resource($process)) {
    return [1, '', sprintf('%s could not be started.', $command[0])];
  }
  // @codeCoverageIgnoreEnd
  fclose($pipes[0]);
  $stdout = (string) stream_get_contents($pipes[1]);
  $stderr = (string) stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);

  return [proc_close($process), $stdout, $stderr];
}

// PHPUnit loads this file to call main() directly, so it runs only outside it.
// @codeCoverageIgnoreStart
if (!class_exists('PHPUnit\Framework\TestCase')) {
  exit(main($argv));
}
// @codeCoverageIgnoreEnd
