<?php

namespace FileFetcherTests\Mock;

use FileFetcher\Processor\Remote;

class FakeRemote extends Remote
{
    protected function getHeaders($url)
    {
        $twoMegaBytes = 20 * 1000 * 1000;
        return Remote::parseHeaders("Accept-Ranges:TRUE\nContent-Length:{$twoMegaBytes}");
    }

    protected function getChunk(string $filePath, int $start, int $end)
    {
        $data = "";
        $numberOfBytes = $end - $start;
        $data = str_pad($data, $numberOfBytes, 'A');
        return !empty(trim($data)) ? $data . PHP_EOL : false;
    }
}
