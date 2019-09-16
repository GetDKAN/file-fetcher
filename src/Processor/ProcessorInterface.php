<?php


namespace FileFetcher\Processor;


use Procrastinator\Result;

interface ProcessorInterface
{
    public function isServerCompatible(array $state): bool;
    public function setupState(array $state): array;
    public function copy(array $state, Result $result, int $timeLimit = PHP_INT_MAX): array;

    /**
     * Whether the processor can deal with time limits.
     * @return bool
     */
    public function isTimeLimitIncompatible(): bool;
}
