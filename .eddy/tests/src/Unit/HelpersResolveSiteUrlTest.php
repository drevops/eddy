<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\DevTools\resolve_site_url;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the resolve_site_url() helper.
 *
 * phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
#[CoversFunction('DrevOps\Eddy\DevTools\resolve_site_url')]
#[Group('p0')]
final class HelpersResolveSiteUrlTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 4) . '/.eddy/tooling/src/helpers.php';
  }

  #[DataProvider('dataProviderResolveSiteUrl')]
  public function testResolveSiteUrl(?string $dotenv_contents, string $expected): void {
    $file = self::$tmp . '/site_url_' . uniqid();
    if ($dotenv_contents !== NULL) {
      file_put_contents($file, $dotenv_contents);
    }

    $this->assertSame($expected, resolve_site_url('localhost', '8000', $file));
  }

  public static function dataProviderResolveSiteUrl(): \Iterator {
    yield 'tunnel url present is preferred' => [
      'dotenv_contents' => "TUNNEL_URL=https://random-words.trycloudflare.com\n",
      'expected' => 'https://random-words.trycloudflare.com',
    ];
    yield 'tunnel url present alongside other vars' => [
      'dotenv_contents' => "WEBSERVER_PORT=8123\nTUNNEL_URL=https://abc.trycloudflare.com\n",
      'expected' => 'https://abc.trycloudflare.com',
    ];
    yield 'no tunnel url falls back to host and port' => [
      'dotenv_contents' => "WEBSERVER_PORT=8123\n",
      'expected' => 'http://localhost:8000',
    ];
    yield 'empty tunnel url falls back to host and port' => [
      'dotenv_contents' => "TUNNEL_URL=\n",
      'expected' => 'http://localhost:8000',
    ];
    yield 'missing dotenv file falls back to host and port' => [
      'dotenv_contents' => NULL,
      'expected' => 'http://localhost:8000',
    ];
  }

  public function testFallbackUsesProvidedHostAndPort(): void {
    $file = self::$tmp . '/site_url_' . uniqid();

    $this->assertSame('http://0.0.0.0:9000', resolve_site_url('0.0.0.0', '9000', $file));
  }

}
