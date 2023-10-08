<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/test',
    ]);

    // Our base version of PHP.
    $rectorConfig->phpVersion(PhpVersion::PHP_74);

    $rectorConfig->sets([
        // Interestingly, LevelSetList::UP_TO_PHP_82 does not preserve PHP 7.4,
        // so we have to specify all the PHP versions leading up to it if we
        // want to keep 7.4 idioms.
        SetList::PHP_74,
        SetList::PHP_80,
        SetList::PHP_81,
        SetList::PHP_82,
        // Please no dead code or unneeded variables.
        SetList::DEAD_CODE,
        // Try to figure out type hints.
        SetList::TYPE_DECLARATION,
    ]);

    $rectorConfig->skip([
        // Don't throw errors on JSON parse problems. Yet.
        // @todo Throw errors and deal with them appropriately.
        JsonThrowOnErrorRector::class,
        // We like our tags. Please don't remove them.
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveUselessVarTagRector::class,
    ]);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);
};
