<?php

namespace FileFetcher\Processor;

use Procrastinator\Result;

class Remote extends AbstractChunkedProcessor
{

    protected function getFileSize(string $filePath): int
    {
        $headers = $this->getHeaders($filePath);
        return $headers['Content-Length'];
    }

    public function isServerCompatible(array $state): bool
    {
        $headers = $this->getHeaders($state['source']);

        if (isset($headers['Accept-Ranges']) && isset($headers['Content-Length'])) {
            return true;
        }

        return false;
    }

    protected function getChunk(string $filePath, int $start, int $end)
    {
        $ch = $this->php->curl_init();
        $this->php->curl_setopt($ch, CURLOPT_URL, $filePath);
        $this->php->curl_setopt($ch, CURLOPT_RANGE, "{$start}-{$end}");
        $this->php->curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        $this->php->curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = $this->php->curl_exec($ch);
        $this->php->curl_close($ch);
        return $result;
    }

    private function getHeaders($url)
    {
        $ch = $this->php->curl_init();
        $this->php->curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $this->php->curl_setopt($ch, CURLOPT_URL, $url);
        $this->php->curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        $this->php->curl_setopt($ch, CURLOPT_HEADER, true);
        $this->php->curl_setopt($ch, CURLOPT_NOBODY, true);

        $headers = $this->parseHeaders($this->php->curl_exec($ch));
        $this->php->curl_close($ch);
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
        } else {
            $value = trim($line);
        }

        return ['key' => $key, 'value' => $value];
    }
}
