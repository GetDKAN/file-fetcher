<?php

namespace FileFetcher\Processor;

use Procrastinator\Result;

class Local extends ProcessorBase implements ProcessorInterface
{
    public function isServerCompatible(array $state): bool
    {
        $path = $state['source'];

        if (file_exists($path) && !is_dir($path)) {
            return true;
        }

        return false;
    }

    public function setupState(array $state): array
    {
        $state['total_bytes'] = PHP_INT_MAX;
        $state['total_bytes'] = filesize($state['source']);
        $state['temporary'] = true;
        $state['destination'] = $this->getTemporaryFilePath($state);

        return $state;
    }

    public function isTimeLimitIncompatible(): bool
    {
        return true;
    }

    public function copy(array $state, Result $result, int $timeLimit = PHP_INT_MAX): array
    {
        copy($state['source'], $state['destination']);
        $result->setStatus(Result::DONE);
        $state['total_bytes_copied'] = $state['total_bytes'] = filesize($state['destination']);

        return ['state' => $state, 'result' => $result];
    }
}
