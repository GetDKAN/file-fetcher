<?php

namespace FileFetcher;

trait TemporaryFilePathFromUrl
{
    private function getTemporaryFilePath(array $state)
    {
        $info = parse_url($state['source']);
        $file_name = "";
        if (isset($info["host"])) {
            $file_name .= str_replace(".", "_", $info["host"]);
        }
        $file_name .= str_replace("/", "_", $info['path']);
        return $this->getTemporaryFile($file_name, $state);
    }

    /**
     * Generate a tmp filepath for a given $uuid.
     *
     * @param string $uuid
     *   UUID.
     *
     * @return string
     */
    private function getTemporaryFile(string $filename, array $state): string
    {
        if ($state['keep_original_filename']) {
            $file_name = basename($state['source']);
            return "{$state['temporary_directory']}/{$file_name}";
        }

        return $state['temporary_directory'] . '/' . $this->sanitizeString($filename);
    }

    private function sanitizeString(string $string): string
    {
        return preg_replace('~[^a-z0-9.]+~', '_', strtolower($string));
    }
}
