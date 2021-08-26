<?php

namespace FileFetcher\Processor;

use FileFetcher\PhpFunctionsBridgeTrait;
use Procrastinator\Result;

class Local implements ProcessorInterface
{

    use PhpFunctionsBridgeTrait;

    /**
     * Local constructor.
     */
    public function __construct()
    {
        $this->initializePhpFunctionsBridge();
    }

    public function isServerCompatible(array $state): bool
    {
        $path = $state['source'];

        if ($this->php->file_exists($path) && !$this->php->is_dir($path)) {
            return true;
        }

        return false;
    }

    public function setupState(array $state): array
    {
        $state['total_bytes'] = PHP_INT_MAX;
        $state['total_bytes'] = $this->php->filesize($state['from']);

        return $state;
    }

    public function isTimeLimitIncompatible(): bool
    {
        return true;
    }

    public function copy(array $state, Result $result, int $timeLimit = PHP_INT_MAX): array
    {
        $this->php->copy($state['from'], $state['to']);
        print_r($state);
        $state['total_bytes_copied'] = $this->php->filesize($state['to']);
        $result->setStatus(Result::DONE);

        return ['state' => $state, 'result' => $result];
    }
}
