<?php

namespace FileFetcherTests\Processor;

use Contracts\Mock\Storage\Memory;
use FileFetcher\FileFetcher;
use FileFetcher\Processor\Remote;
use PHPUnit\Framework\TestCase;
use Procrastinator\Result;

class RemoteTest extends TestCase
{

    public function testCopyALocalFileWithRemoteProcessor()
    {

        $fetcher = FileFetcher::get(
            "1",
            new Memory(),
            [
                "filePath" => 'http://notreal.blah/notacsv.csv',
                "processors" => [FakeRemote::class]
            ]
        );

        $fetcher->setTimeLimit(1);

        $counter = 0;
        do {
            $result = $fetcher->run();
            $counter++;
        }
        while ($result->getStatus() == Result::STOPPED);

        $state = $fetcher->getState();

        print_r($state);
        print_r($result);
        print_R($counter);

        $this->assertTrue(true);


        unlink($state['destination']);
    }
}

class FakeRemote extends Remote {
    protected function getHeaders($url)
    {
        $twoMegaBytes = 20 * 1000 * 1000;
        return ['Accept-Ranges' => "TRUE", 'Content-Length' => $twoMegaBytes];
    }

    protected function getChunk(string $filePath, int $start, int $end)
    {
        print_r("{$start}-{$end}" . PHP_EOL);
        sleep(1);
        $data = "";
        $numberOfBytes = $end - $start;
        for ($i = 0; $i < $numberOfBytes; $i++) {
            $data .= "A";
        }
        return !empty(trim($data)) ? $data : false;
    }

}