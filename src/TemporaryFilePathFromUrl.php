<?php

namespace FileFetcher;

trait TemporaryFilePathFromUrl
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
    private function getTemporaryFilePath(array $state): string
    {
        if ($state['keep_original_filename']) {
          return $this->getTemporaryFileOriginalName($state);
        }
        else {
          return $this->getTemporaryFile($state);
        }
    }

    private function getTemporaryFileOriginalName(array $state): string
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
}
