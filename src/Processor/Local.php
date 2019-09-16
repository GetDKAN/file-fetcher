<?php

namespace FileFetcher\Processor;

use Procrastinator\Result;

class Local implements ProcessorInterface
{
    public function isServerCompatible(array $state): bool
    {
        $file = new \SplFileObject($state['source']);
        return $file->isFile();
    }

    public function setupState(array $state): array
    {
        $size = filesize($state['source']);
        $state['total_bytes'] = $size;
        $state['total_bytes_copied'] = $size;
        return $state;
    }

    public function isTimeLimitIncompatible(): bool
    {
        return true;
    }

    public function copy(array $state, Result $result, int $timeLimit = PHP_INT_MAX): array
    {
        $result->setStatus(Result::DONE);
        return ['state' => $state, 'result' => $result];
    }
}