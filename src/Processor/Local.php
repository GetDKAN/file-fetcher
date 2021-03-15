<?php

namespace FileFetcher\Processor;

use Procrastinator\Result;

class Local extends AbstractChunkedProcessor
{

    protected function getFileSize(string $filePath): int
    {
        return $this->php->filesize($filePath);
    }

    public function isServerCompatible(array $state): bool
    {
        $path = $state['source'];

        if ($this->php->file_exists($path)) {
            if (!$this->php->is_dir($path)) {
                return true;
            }
            throw new \Exception('A real path was given but it does not point to a file.');
        }

        return false;
    }

    protected function getChunk(string $filePath, int $start, int $end)
    {
        $fp = fopen($filePath, 'r');
        fseek($fp, $start);
        $data = fgets($fp, $end - $start);
        fclose($fp);
        return $data;
    }


}
