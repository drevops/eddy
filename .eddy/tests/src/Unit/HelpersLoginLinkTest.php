<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\login_link;
use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversFunction('DrevOps\Eddy\Tooling\login_link')]
#[RunTestsInSeparateProcesses]
#[Group('p0')]
final class HelpersLoginLinkTest extends UnitTestCase {

  #[DataProvider('dataProviderLoginLink')]
  public function testLoginLink(string $drush_version, string $expected_options): void {
    $prefix = 'build/vendor/bin/drush -r ' . escapeshellarg(File::cwd() . '/build/web') . ' -y ';
    $site_url = 'https://abc.trycloudflare.com';

    $this->mockPassthruMultiple([
      ['cmd' => $prefix . 'status --field=drush-version', 'output' => $drush_version . "\n"],
      ['cmd' => $prefix . 'uli -l ' . escapeshellarg($site_url) . $expected_options, 'output' => $site_url . "/user/reset/1/1790000000/abc/login\n"],
    ]);

    $this->assertSame($site_url . '/user/reset/1/1790000000/abc/login', login_link($site_url));
  }

  public static function dataProviderLoginLink(): \Iterator {
    yield 'Drush 12' => ['12.5.3', ' --no-browser'];
    yield 'Drush 13' => ['13.8.0', ' --no-browser'];
    yield 'Drush 14' => ['14.0.0', ''];
    yield 'Drush 14 development branch' => ['14.x-dev', ''];
    yield 'Drush 15' => ['15.0.0', ''];
    // A version that can't be read gets '--no-browser'. Drush 14 then fails on
    // the option, and Drush 12 and 13 open no browser.
    yield 'unreadable version' => ['', ' --no-browser'];
  }

}
