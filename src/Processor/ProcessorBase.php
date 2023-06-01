<?php

namespace FileFetcher\Processor;

use FileFetcher\PhpFunctionsBridgeTrait;
use FileFetcher\TemporaryFilePathFromUrl;
use Procrastinator\Result;

abstract class ProcessorBase
{
    /**
     * Get temporary file path, depending on flag keep_original_filename value.
     *
     * @param array $state
     *   State.
     *
     * @return string
     *   Temporary file path.
     */
    protected function getTemporaryFilePath(array $state): string
    {
        if ($state['keep_original_filename']) {
            return $this->getTemporaryFileOriginalName($state);
        } else {
            return $this->getTemporaryFile($state);
        }
    }

    protected function getTemporaryFileOriginalName(array $state): string
    {
        $file_name = basename($state['source']);
        return "{$state['temporary_directory']}/{$file_name}";
    }

    private function getTemporaryFile(array $state): string
    {
        $info = parse_url($state['source']);
        $file_name = "";
        if (isset($info["host"])) {
            $file_name .= str_replace(".", "_", $info["host"]);
        }
        $file_name .= str_replace("/", "_", $info['path']);
        return $state['temporary_directory'] . '/' . $this->sanitizeString($file_name);
    }

    private function sanitizeString(string $string): string
    {
        return preg_replace('~[^a-z0-9.]+~', '_', strtolower($string));
    }

    public function isTimeLimitIncompatible(): bool
    {
        return false;
    }
}
