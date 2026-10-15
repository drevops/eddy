<?php

declare(strict_types=1);

namespace DrevOps\Eddy\Tests\Unit;

use function DrevOps\Eddy\Tooling\version_satisfies;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for version_satisfies().
 */
#[CoversFunction('DrevOps\Eddy\Tooling\version_satisfies')]
#[Group('p0')]
final class HelpersVersionSatisfiesTest extends UnitTestCase {

  #[DataProvider('dataProviderVersionSatisfies')]
  public function testVersionSatisfies(string $version, string $constraint, ?bool $expected): void {
    $this->assertSame($expected, version_satisfies($version, $constraint));
  }

  public static function dataProviderVersionSatisfies(): \Iterator {
    yield 'at the lower bound' => ['8.5.0', '>=8.5', TRUE];
    yield 'above the lower bound' => ['8.5.4', '>=8.5', TRUE];
    yield 'below the lower bound' => ['8.4.17', '>=8.5', FALSE];
    yield 'lower bound with 3 numbers' => ['8.3.0', '>=8.3.0', TRUE];
    yield 'lower bound with 4 numbers' => ['8.3.0', '>=8.3.0.0', TRUE];
    yield 'lower bound with a v prefix' => ['8.3.0', '>=v8.3', TRUE];
    yield 'operator apart from its version' => ['8.4.17', '>= 8.5', FALSE];
    yield 'greater than' => ['8.3.1', '>8.3', TRUE];
    yield 'not greater than' => ['8.3.0', '>8.3', FALSE];
    yield 'less than' => ['8.4.17', '<9', TRUE];
    yield 'not less than' => ['9.0.0', '<9', FALSE];
    yield 'at most' => ['8.4.0', '<=8.4', TRUE];
    yield 'above the most' => ['8.4.1', '<=8.4', FALSE];
    yield 'exact version' => ['8.4.0', '8.4', TRUE];
    yield 'exact version with =' => ['8.4.0', '=8.4.0', TRUE];
    yield 'exact version with ==' => ['8.4.1', '==8.4.0', FALSE];
    yield 'excluded version' => ['8.4.0', '!=8.4.0', FALSE];
    yield 'excluded version with <>' => ['8.4.1', '<>8.4.0', TRUE];
    yield 'range joined with a space' => ['8.4.17', '>=8.3 <9', TRUE];
    yield 'range joined with a comma' => ['9.0.0', '>=8.3, <9', FALSE];
    yield 'first alternative' => ['8.3.5', '>=8.3 <8.4 || >=8.5', TRUE];
    yield 'second alternative' => ['8.5.0', '>=8.3 <8.4 || >=8.5', TRUE];
    yield 'no alternative' => ['8.4.17', '>=8.3 <8.4 || >=8.5', FALSE];
    yield 'alternatives with a single pipe' => ['8.5.0', '<8.1 | >=8.5', TRUE];
    yield 'surrounding whitespace' => ['8.5.0', '  >=8.5  ', TRUE];
    yield 'caret' => ['8.5.0', '^8.3', NULL];
    yield 'tilde' => ['8.5.0', '~8.3', NULL];
    yield 'wildcard' => ['8.5.0', '8.*', NULL];
    yield 'hyphen range' => ['8.5.0', '8.3 - 8.5', NULL];
    yield 'stability flag' => ['8.5.0', '>=8.5@dev', NULL];
    yield 'empty' => ['8.5.0', '', NULL];
    yield 'unknown form after a known one' => ['8.5.0', '>=8.3 || ^8.5', NULL];
  }

}
