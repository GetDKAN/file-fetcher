<?php

namespace FileFetcher\Processor;

class Local extends AbstractChunkedProcessor
{

    protected function getFileSize(string $filePath): int
    {
        return $this->php->filesize($filePath);
    }

    public function isServerCompatible(array $state): bool
    {
        $path = $state['source'];

        if ($this->php->file_exists($path) && !$this->php->is_dir($path)) {
            return true;
        }

        return false;
    }

    protected function getChunk(string $filePath, int $start, int $end)
    {
        $fp = fopen($filePath, 'r');
        fseek($fp, $start);
        $bytesToCopy = $end - $start;
        $data = fread($fp, $bytesToCopy);
        fclose($fp);
        return $data;
    }


}
