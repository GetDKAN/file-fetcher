<?php

namespace FileFetcher\Processor;

use FileFetcher\TemporaryFilePathFromUrl;
use Procrastinator\Result;

class LastResort implements ProcessorInterface
{
    use TemporaryFilePathFromUrl;

    public function isServerCompatible(array $state): bool
    {
        return true;
    }

    public function setupState(array $state): array
    {
        $state['total_bytes'] = PHP_INT_MAX;
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
        // 1 MB.
        $bytesToRead = 1024 * 1000;

        $from = $state['source'];
        $to = $state['destination'];
        $this->deleteFile($to);

        $bytesCopied = 0;
        $fin = fopen($from, "rb");
        $fout = fopen($to, "w");
        while (!feof($fin)) {
            $bytesCopied += fwrite($fout, fread($fin, $bytesToRead));
        }
        fclose($fin);
        fclose($fout);
        $state['total_bytes_copied'] = $bytesCopied;
        $state['total_bytes'] = $bytesCopied;
        $result->setStatus(Result::DONE);

        return ['state' => $state, 'result' => $result];
    }

    private function deleteFile($file)
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}