<?php

/**
 * @file
 * Rector configuration.
 */

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchMethodCallReturnTypeRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\RemoveExpectAnyFromMockRector;
use Rector\PHPUnit\PHPUnit60\Rector\ClassMethod\AddDoesNotPerformAssertionToNonAssertingTestRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
  ->withPaths([
    __DIR__ . '/src/**',
    __DIR__ . '/../assets/update-assets.php',
    __DIR__ . '/../../.eddy/tooling/src/eddy-assemble',
    __DIR__ . '/../../.eddy/tooling/src/eddy-browser-start',
    __DIR__ . '/../../.eddy/tooling/src/eddy-browser-stop',
    __DIR__ . '/../../.eddy/tooling/src/eddy-deploy',
    __DIR__ . '/../../.eddy/tooling/src/helpers.php',
    __DIR__ . '/../../.eddy/tooling/src/eddy-info',
    __DIR__ . '/../../.eddy/tooling/src/eddy-provision',
    __DIR__ . '/../../.eddy/tooling/src/eddy-qrcode',
    __DIR__ . '/../../.eddy/tooling/src/eddy-start',
    __DIR__ . '/../../.eddy/tooling/src/eddy-stop',
    __DIR__ . '/../../install.php',
    __DIR__ . '/../../scripts/eddy-tooling',
  ])
  ->withPhpSets(php83: TRUE)
  ->withPreparedSets(
    deadCode: TRUE,
    codeQuality: TRUE,
    codingStyle: TRUE,
    typeDeclarations: TRUE,
    naming: TRUE,
    instanceOf: TRUE,
    earlyReturn: TRUE,
    phpunitCodeQuality: TRUE,
  )
  ->withComposerBased(phpunit: TRUE)
  ->withRules([
    DeclareStrictTypesRector::class,
  ])
  ->withSkip([
    CatchExceptionNameMatchingTypeRector::class,
    ChangeSwitchToMatchRector::class,
    CompleteDynamicPropertiesRector::class,
    InlineArrayReturnAssignRector::class,
    NewlineAfterStatementRector::class,
    NewlineBeforeNewAssignSetRector::class,
    NewlineBetweenClassLikeStmtsRector::class,
    RemoveAlwaysTrueIfConditionRector::class,
    RenameForeachValueVariableToMatchExprVariableRector::class,
    RenameForeachValueVariableToMatchMethodCallReturnTypeRector::class,
    RenameVariableToMatchMethodCallReturnTypeRector::class,
    RenameVariableToMatchNewTypeRector::class,
    SimplifyEmptyCheckOnEmptyArrayRector::class,
    StringClassNameToClassConstantRector::class,
    // php-mock function mocks are stubbed through 'expects()', so removing
    // 'expects($this->any())' leaves a call the mock proxy does not have.
    RemoveExpectAnyFromMockRector::class,
    // The rule cannot see assertions made through php-mock expectations, so
    // it marks tests that assert through them as performing none.
    AddDoesNotPerformAssertionToNonAssertingTestRector::class,
    '*/vendor/*',
    '*/node_modules/*',
  ])
  ->withFileExtensions([
    'php',
    'inc',
  ])
  ->withImportNames(importNames: TRUE, importDocBlockNames: FALSE, importShortClasses: FALSE);
