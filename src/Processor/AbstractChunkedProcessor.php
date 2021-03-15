<?php

namespace FileFetcher\Processor;

use FileFetcher\PhpFunctionsBridgeTrait;
use FileFetcher\TemporaryFilePathFromUrl;
use Procrastinator\Result;

abstract class AbstractChunkedProcessor implements ProcessorInterface
{
    use PhpFunctionsBridgeTrait;
    use TemporaryFilePathFromUrl;

    abstract public function isServerCompatible(array $state): bool;
    abstract protected function getFileSize(string $filePath): int;
    abstract protected function getChunk(string $filePath, int $start, int $end);
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->initializePhpFunctionsBridge();
    }


    public function setupState(array $state): array
    {
        $state['destination'] = $this->getTemporaryFilePath($state);
        $state['temporary'] = true;
        $state['total_bytes'] = $this->getFileSize($state['source']);

        if (file_exists($state['destination'])) {
            $state['total_bytes_copied'] = filesize($state['destination']);
        }

        return $state;
    }

    public function isTimeLimitIncompatible(): bool
    {
        return false;
    }

    public function copy(array $state, Result $result, int $timeLimit = PHP_INT_MAX): array
    {
        $destinationFile = $state['destination'];
        $total = $state['total_bytes_copied'];

        $expiration = time() + $timeLimit;

        while ($chunk = $this->getTheChunk($state)) {
            $bytesWritten = $this->createOrAppend($destinationFile, $chunk);

            if ($bytesWritten !== strlen($chunk)) {
                throw new \RuntimeException(
                    "Unable to fetch {$state['source']}. " .
                    " Reason: Failed to write to destination " . $destinationFile,
                    0
                );
            }

            $total += $bytesWritten;
            $state['total_bytes_copied'] = $total;

            $currentTime = time();
            if ($currentTime > $expiration) {
                $result->setStatus(Result::STOPPED);
                return ['state' => $state, 'result' => $result];
            }
        }

        $result->setStatus(Result::DONE);
        return ['state' => $state, 'result' => $result];
    }

    private function createOrAppend($filePath, $chunk)
    {
        if (!file_exists($filePath)) {
            $bytesWritten = file_put_contents($filePath, $chunk);
        } else {
            $bytesWritten = file_put_contents($filePath, $chunk, FILE_APPEND);
        }
        return $bytesWritten;
    }

    private function getTheChunk(array $state)
    {
        // 10 MB.
        $bytesToRead = 10 * 1000 * 1000;

        $filePath = $state['source'];
        $start = $state['total_bytes_copied'];
        $end = $start + $bytesToRead;

        if ($end > $state['total_bytes']) {
            $end = $state['total_bytes'];
        }

        if ($start == $end) {
            return false;
        }

        return $this->getChunk($filePath, $start, $end);
    }

}