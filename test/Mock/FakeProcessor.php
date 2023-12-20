<?php

namespace FileFetcherTests\Mock;

use FileFetcher\Processor\Local;
use FileFetcher\Processor\ProcessorInterface;
use Procrastinator\Result;

class FakeProcessor implements ProcessorInterface
{
    public function isServerCompatible(array $state): bool
    {
        return true;
    }

    public function setupState(array $state): array
    {
        return $state;
    }

    public function copy(array $state, Result $result, int $timeLimit = PHP_INT_MAX): array
    {
        return ['state' => $state, 'result' => $result];
    }

    public function isTimeLimitIncompatible(): bool
    {
        return false;
    }
}
