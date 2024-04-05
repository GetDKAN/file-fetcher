<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddMethodCallBasedStrictParamTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ArrayShapeFromConstantArrayReturnRector;

return static function (RectorConfig $rectorConfig): void {

    $rectorConfig->phpVersion(PhpVersion::PHP_74);

    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/test',
    ]);

    // Our base version of PHP.
    $rectorConfig->phpVersion(PhpVersion::PHP_74);

    $rectorConfig->sets([
        // Please no dead code or unneeded variables.
        SetList::DEAD_CODE,
        // Try to figure out type hints.
        SetList::TYPE_DECLARATION,
        // Bring us up to PHP 7.4.
        LevelSetList::UP_TO_PHP_74,
    ]);

    $rectorConfig->skip([
        // Don't throw errors on JSON parse problems. Yet.
        // @todo Throw errors and deal with them appropriately.
        JsonThrowOnErrorRector::class,
        // We like our tags. Please don't remove them.
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveUselessVarTagRector::class,
        ArrayShapeFromConstantArrayReturnRector::class,
        AddMethodCallBasedStrictParamTypeRector::class,
    ]);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);
};
