<?php


namespace FileFetcher;


trait Headers
{
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
        } else {
            $value = trim($line);
        }

        return ['key' => $key, 'value' => $value];
    }
}
