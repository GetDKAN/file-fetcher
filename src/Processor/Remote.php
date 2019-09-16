<?php

namespace FileFetcher\Processor;

use FileFetcher\TemporaryFilePathFromUrl;
use Procrastinator\Result;

class Remote implements ProcessorInterface
{
    use TemporaryFilePathFromUrl;

    public function isServerCompatible(array $state): bool
    {
        $headers = $this->getHeaders($state['source']);

        if (isset($headers['Accept-Ranges']) && isset($headers['Content-Length'])) {
            return true;
        }

        return false;
    }

    public function setupState(array $state): array
    {
        $state['destination'] = $this->getTemporaryFilePath($state);
        $state['temporary'] = true;
        $state['total_bytes'] = $this->getRemoteFileSize($state['source']);

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

        while ($chunk = $this->getChunk($state)) {
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

    private function getChunk(array $state)
    {
        // 1 MB.
        $bytesToRead = 1024 * 1000;

        $url = $state['source'];
        $start = $state['total_bytes_copied'];
        $end = $start + $bytesToRead;

        if ($end > $state['total_bytes']) {
            $end = $state['total_bytes'];
        }

        if ($start == $end) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RANGE, "{$start}-{$end}");
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function getHeaders($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        $headers = $this->parseHeaders(curl_exec($ch));
        curl_close($ch);
        return $headers;
    }

    private function parseHeaders($string)
    {
        $headers = [];
        $lines = explode(PHP_EOL, $string);
        foreach ($lines as $line) {
            $line = trim($line);
            $keyvalue = $this->getKeyValueFromLine($line);
            $headers[$keyvalue['key']] = $keyvalue['value'];

        }
        return $headers;
    }

    private function getKeyValueFromLine($line): array
    {
        $key = null;
        $value = null;

        $parts = explode(":", $line);
        if (count($parts) > 1) {
            $key = array_shift($parts);
            $value = trim(implode(":", $parts));
        }
        else {
            $value = trim($line);
        }

        return ['key' => $key, 'value' => $value];
    }

    private function getRemoteFileSize($url)
    {
        $headers = $this->getHeaders($url);
        return $headers['Content-Length'];
    }
}
