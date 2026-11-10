<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\File;
use Laravel\SerializableClosure\SerializableClosure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

use function DrevOps\Eddy\Installer\is_signed_by;
use function DrevOps\Eddy\Installer\main;
use function DrevOps\Eddy\Installer\verify_checksums;
use function DrevOps\Eddy\Installer\verify_release;

/**
 * Tests for '.eddy/installer/verify-release.php'.
 *
 * The fixtures hold 2 throwaway keys and a release directory signed by the
 * first. Each test runs gpg with its own empty home directory, so it never
 * reads or changes the home directory of the user.
 */
#[Group('p0')]
final class VerifyReleaseTest extends UnitTestCase {

  protected const string TEST_FINGERPRINT = 'E07D1352460C577ED3162319193A7081645B6D4B';

  protected const string OTHER_FINGERPRINT = 'B4FC0C20D14AAECB7B229F8226563E92DCB376E2';

  protected const string INSTALLER_SHA256 = '4fbdabc7f80f7ec655798b8b588b9e8fd120ec3a139ef82ee7f6cd384d11f296';

  protected string $release;

  protected function setUp(): void {
    parent::setUp();

    require_once self::rootDir() . '/.eddy/installer/verify-release.php';

    File::mkdir(self::$tmp . '/gnupg', 0700);
    self::envSet('GNUPGHOME', self::$tmp . '/gnupg');

    $this->release = self::$tmp . '/release';
    File::copy(self::fixture('release'), $this->release);
  }

  public function testVerifyRelease(): void {
    $output = $this->capture(fn() => verify_release($this->release, self::fixture('test-key.asc'), self::TEST_FINGERPRINT));

    $this->assertSame(implode(PHP_EOL, ['SHA256SUMS is signed by the Eddy signing key ' . self::TEST_FINGERPRINT . '.', 'installer.txt: OK', 'template.txt: OK']) . PHP_EOL, $output);
  }

  #[DataProvider('dataProviderVerifyReleaseFails')]
  public function testVerifyReleaseFails(SerializableClosure $prepare, string $key, string $expected): void {
    self::cu($prepare)($this->release);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(str_replace('{release}', $this->release, $expected));

    $this->capture(fn() => verify_release($this->release, self::fixture($key), self::TEST_FINGERPRINT));
  }

  public static function dataProviderVerifyReleaseFails(): \Iterator {
    $not_signed = '{release}/SHA256SUMS is not signed by the Eddy signing key ' . self::TEST_FINGERPRINT . '.';

    yield 'no SHA256SUMS' => [self::cw(fn(string $dir) => File::remove($dir . '/SHA256SUMS')), 'test-key.asc', '{release}/SHA256SUMS does not exist.'];
    yield 'no SHA256SUMS.asc' => [self::cw(fn(string $dir) => File::remove($dir . '/SHA256SUMS.asc')), 'test-key.asc', '{release}/SHA256SUMS.asc does not exist.'];
    yield 'a signature by a key in the keyring other than the pinned key' => [self::cw(fn(string $dir): bool => File::copy(self::fixture('other-key-signature.asc'), $dir . '/SHA256SUMS.asc')), 'other-key.asc', $not_signed];
    yield 'a signature by a key that is not in the keyring' => [self::cw(fn(string $dir): bool => File::copy(self::fixture('other-key-signature.asc'), $dir . '/SHA256SUMS.asc')), 'test-key.asc', $not_signed];
    yield 'SHA256SUMS changed after the signature' => [self::cw(fn(string $dir) => File::append($dir . '/SHA256SUMS', str_repeat('0', 64) . "  extra.txt\n")), 'test-key.asc', $not_signed];
    yield 'a signature that is not a signature' => [self::cw(fn(string $dir) => File::dump($dir . '/SHA256SUMS.asc', "Not a signature.\n")), 'test-key.asc', $not_signed];
    yield 'a changed file' => [self::cw(fn(string $dir) => File::append($dir . '/template.txt', "Changed.\n")), 'test-key.asc', '{release}/template.txt does not match its checksum in SHA256SUMS.'];
    yield 'a missing file' => [self::cw(fn(string $dir) => File::remove($dir . '/installer.txt')), 'test-key.asc', '{release}/installer.txt, listed in SHA256SUMS, does not exist.'];
  }

  public function testVerifyReleaseFailsWithoutKey(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(self::$tmp . '/missing.asc does not exist.');

    verify_release($this->release, self::$tmp . '/missing.asc', self::TEST_FINGERPRINT);
  }

  public function testVerifyReleaseFailsOnInvalidKey(): void {
    File::dump(self::$tmp . '/invalid.asc', "Not a key.\n");

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('gpg could not read the public key ' . self::$tmp . '/invalid.asc');

    verify_release($this->release, self::$tmp . '/invalid.asc', self::TEST_FINGERPRINT);
  }

  #[DataProvider('dataProviderVerifyReleaseFailsWithoutDirectory')]
  public function testVerifyReleaseFailsWithoutDirectory(string $dir): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Pass the directory that holds SHA256SUMS and SHA256SUMS.asc.');

    verify_release(str_replace('{tmp}', self::$tmp, $dir), self::fixture('test-key.asc'), self::TEST_FINGERPRINT);
  }

  public static function dataProviderVerifyReleaseFailsWithoutDirectory(): \Iterator {
    yield 'no directory' => [''];
    yield 'a missing directory' => ['{tmp}/missing'];
  }

  #[DataProvider('dataProviderIsSignedBy')]
  public function testIsSignedBy(string $status, bool $expected): void {
    $this->assertSame($expected, is_signed_by($status, self::TEST_FINGERPRINT));
  }

  public static function dataProviderIsSignedBy(): \Iterator {
    $valid = static fn(string $signer, string $primary): string => sprintf('[GNUPG:] VALIDSIG %s 2026-11-10 1794268734 0 4 0 22 10 00 %s', $signer, $primary);

    yield 'a signature by the primary key' => [$valid(self::TEST_FINGERPRINT, self::TEST_FINGERPRINT), TRUE];
    yield 'a signature by a subkey of the primary key' => [$valid(self::OTHER_FINGERPRINT, self::TEST_FINGERPRINT), TRUE];
    yield 'a signature by another key' => [$valid(self::OTHER_FINGERPRINT, self::OTHER_FINGERPRINT), FALSE];
    yield 'the status lines of a valid signature' => [implode("\n", ['[GNUPG:] NEWSIG', '[GNUPG:] GOODSIG 193A7081645B6D4B Eddy Test Signing', $valid(self::TEST_FINGERPRINT, self::TEST_FINGERPRINT), '[GNUPG:] TRUST_UNDEFINED 0 pgp']), TRUE];
    yield 'a bad signature' => ['[GNUPG:] BADSIG 193A7081645B6D4B Eddy Test Signing', FALSE];
    yield 'the fingerprint in a line other than VALIDSIG' => ['[GNUPG:] GOODSIG ' . self::TEST_FINGERPRINT . ' ' . self::TEST_FINGERPRINT, FALSE];
    yield 'the fingerprint in text that is not a status line' => ['gpg: VALIDSIG ' . self::TEST_FINGERPRINT . ' ' . self::TEST_FINGERPRINT, FALSE];
    yield 'a truncated VALIDSIG line' => ['[GNUPG:] VALIDSIG', FALSE];
    yield 'no output' => ['', FALSE];
  }

  #[DataProvider('dataProviderVerifyChecksumsFails')]
  public function testVerifyChecksumsFails(string $sums, string $expected): void {
    File::dump($this->release . '/SHA256SUMS', $sums);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected);

    verify_checksums($this->release);
  }

  public static function dataProviderVerifyChecksumsFails(): \Iterator {
    $sha256 = str_repeat('a', 64);

    yield 'no lines' => ['', 'SHA256SUMS holds a line that is not a checksum of a file in the directory: "".'];
    yield 'a blank line between lines' => [self::INSTALLER_SHA256 . "  installer.txt\n\n" . $sha256 . "  template.txt\n", 'SHA256SUMS holds a line that is not a checksum of a file in the directory: "".'];
    yield 'an uppercase checksum' => [strtoupper($sha256) . "  installer.txt\n", 'not a checksum of a file in the directory: "' . strtoupper($sha256) . '  installer.txt".'];
    yield 'a short checksum' => ["abc  installer.txt\n", 'not a checksum of a file in the directory: "abc  installer.txt".'];
    yield 'a path in a subdirectory' => [$sha256 . "  sub/installer.txt\n", 'not a checksum of a file in the directory: "' . $sha256 . '  sub/installer.txt".'];
    yield 'a path outside the directory' => [$sha256 . "  ../installer.txt\n", 'not a checksum of a file in the directory: "' . $sha256 . '  ../installer.txt".'];
    yield 'the parent directory' => [$sha256 . "  ..\n", 'not a checksum of a file in the directory: "' . $sha256 . '  ..".'];
    yield 'no separator' => [$sha256 . "installer.txt\n", 'not a checksum of a file in the directory: "' . $sha256 . 'installer.txt".'];
  }

  public function testVerifyChecksumsBinaryMode(): void {
    File::dump($this->release . '/SHA256SUMS', hash_file('sha256', $this->release . '/installer.txt') . " *installer.txt\n");

    $this->assertSame(['installer.txt'], verify_checksums($this->release));
  }

  public function testMain(): void {
    $output = $this->capture(fn() => $this->assertSame(0, main(['verify-release.php', $this->release], self::fixture('test-key.asc'), self::TEST_FINGERPRINT)));

    $this->assertStringContainsString('template.txt: OK', $output);
  }

  public function testMainFails(): void {
    $output = $this->capture(fn() => $this->assertSame(1, main(['verify-release.php'])));

    $this->assertSame('Error: Pass the directory that holds SHA256SUMS and SHA256SUMS.asc.' . PHP_EOL, $output);
  }

  public function testPublishedKeyHasPinnedFingerprint(): void {
    $this->assertSame(self::rootDir() . '/install-key.asc', self::locationsRealpath(self::installerConstant('PUBLIC_KEY')));

    $output = [];
    exec('gpg --batch --show-keys --with-colons ' . escapeshellarg(self::installerConstant('PUBLIC_KEY')) . ' 2>&1', $output, $exit_code);

    $this->assertSame(0, $exit_code, implode(PHP_EOL, $output));
    $this->assertContains('fpr:::::::::' . self::installerConstant('SIGNING_KEY_FINGERPRINT') . ':', $output, 'install-key.asc holds the public key of the pinned fingerprint.');
  }

  public function testDocumentationShowsPinnedFingerprint(): void {
    // The pages show the fingerprint the way 'gpg --fingerprint' prints it.
    $groups = str_split(self::installerConstant('SIGNING_KEY_FINGERPRINT'), 4);
    $grouped = implode(' ', array_slice($groups, 0, 5)) . '  ' . implode(' ', array_slice($groups, 5));

    foreach (['.eddy/docs/content/installation.mdx', '.eddy/CLAUDE.md'] as $file) {
      $this->assertStringContainsString($grouped, File::read(self::rootDir() . '/' . $file), sprintf('%s shows the fingerprint of the signing key.', $file));
    }
  }

  protected static function fixture(string $name): string {
    return self::fixturesDir() . '/verify-release/' . $name;
  }

  /**
   * Read a constant of the verifier.
   *
   * The coding standard removes a 'use const' line that Rector adds, so the
   * constants are read by name.
   */
  protected static function installerConstant(string $name): string {
    $value = constant('DrevOps\\Eddy\\Installer\\' . $name);
    self::assertIsString($value);

    return $value;
  }

}
