<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use AlexSkrypnyk\File\Testing\DirectoryAssertionsTrait;
use AlexSkrypnyk\File\Testing\FileAssertionsTrait;
use AlexSkrypnyk\PhpunitHelpers\Traits\EnvTrait;
use AlexSkrypnyk\PhpunitHelpers\Traits\SerializableClosureTrait;
use AlexSkrypnyk\PhpunitHelpers\UnitTestCase as UpstreamUnitTestCase;
use DrevOps\Eddy\Tests\Traits\MockTrait;

/**
 * UnitTestCase fixture class.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment.Missing
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */
abstract class UnitTestCase extends UpstreamUnitTestCase {

  use DirectoryAssertionsTrait;
  use EnvTrait;
  use FileAssertionsTrait;
  use MockTrait;
  use SerializableClosureTrait;

  protected function tearDown(): void {
    // Reset before the mock assertions so a failed mock expectation cannot
    // leave environment variables set for the next test.
    self::envReset();
    $this->mockTearDown();
    parent::tearDown();
  }

  /**
   * Build the command that kill_port() runs for a port.
   */
  protected static function killPortCommand(string $port): string {
    return sprintf('lsof -ti tcp:%s -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null', escapeshellarg($port));
  }

}
