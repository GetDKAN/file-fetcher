<?php

namespace FileFetcher\Processor;

class Remote extends AbstractChunkedProcessor
{
    protected const HTTP_URL_REGEX = '%^(?:https?://)?(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%iu';

    protected function getFileSize(string $filePath): int
    {
        $headers = $this->getHeaders($filePath);
        return $headers['Content-Length'];
    }

    public function isServerCompatible(array $state): bool
    {
        return preg_match(self::HTTP_URL_REGEX, $state['source']) === 1;
    }

    protected function getChunk(string $filePath, int $start, int $end)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $filePath);
        curl_setopt($ch, CURLOPT_RANGE, "{$start}-{$end}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = $this->php->curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    protected function getHeaders($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        $headers = $this->parseHeaders($this->php->curl_exec($ch));
        curl_close($ch);
        return $headers;
    }

    public static function parseHeaders($string)
    {
        $headers = [];
        $lines = explode(PHP_EOL, $string);
        foreach ($lines as $line) {
            $line = trim($line);
            $keyvalue = self::getKeyValueFromLine($line);
            $headers[$keyvalue['key']] = $keyvalue['value'];
        }
        return $headers;
    }

    private static function getKeyValueFromLine($line): array
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
