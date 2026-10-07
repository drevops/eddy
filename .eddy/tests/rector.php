<?php

/**
 * @file
 * Rector configuration.
 */

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodeQuality\Rector\FuncCall\SimplifyRegexPatternRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchMethodCallReturnTypeRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
  ->withPaths([
    __DIR__ . '/src/**',
    __DIR__ . '/../assets/update-assets.php',
    __DIR__ . '/../../.eddy/tooling/src/eddy-assemble',
    __DIR__ . '/../../.eddy/tooling/src/eddy-browser',
    __DIR__ . '/../../.eddy/tooling/src/eddy-deploy',
    __DIR__ . '/../../.eddy/tooling/src/helpers.php',
    __DIR__ . '/../../.eddy/tooling/src/eddy-info',
    __DIR__ . '/../../.eddy/tooling/src/eddy-provision',
    __DIR__ . '/../../.eddy/tooling/src/eddy-qrcode',
    __DIR__ . '/../../.eddy/tooling/src/eddy-start',
    __DIR__ . '/../../.eddy/tooling/src/eddy-stop',
    __DIR__ . '/../../init.php',
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
  ->withSets([
    PHPUnitSetList::PHPUNIT_110,
  ])
  ->withRules([
    DeclareStrictTypesRector::class,
  ])
  ->withSkip([
    AddOverrideAttributeToOverriddenMethodsRector::class,
    CatchExceptionNameMatchingTypeRector::class,
    ChangeSwitchToMatchRector::class,
    CompleteDynamicPropertiesRector::class,
    CountArrayToEmptyArrayComparisonRector::class,
    DisallowedEmptyRuleFixerRector::class,
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
    // Each update replaces the verbatim copy of the prompt library between
    // the '@embed-start' and '@embed-end' markers, so any rewrite there is
    // discarded. Rector cannot skip a region, so only the rules that the
    // minified library triggers are skipped for the whole of 'init.php'.
    NullToStrictStringFuncCallArgRector::class => [
      __DIR__ . '/../../init.php',
    ],
    SimplifyRegexPatternRector::class => [
      __DIR__ . '/../../init.php',
    ],
    '*/vendor/*',
    '*/node_modules/*',
  ])
  ->withFileExtensions([
    'php',
    'inc',
  ])
  ->withImportNames(importNames: TRUE, importDocBlockNames: FALSE, importShortClasses: FALSE);
