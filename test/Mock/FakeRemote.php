<?php

namespace FileFetcherTests\Mock;

use FileFetcher\Processor\Remote;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class FakeRemote extends Remote
{
    protected function getClient(): Client
    {
        $handlerStack = HandlerStack::create($this->getMockHandler());
        return new Client(['handler' => $handlerStack]);
    }

    private function getMockHandler()
    {
        $data = "";
        $numberOfBytes = $end - $start;
        $data = str_pad($data, $numberOfBytes, 'A');
        return !empty(trim($data)) ? $data . PHP_EOL : false;
    }
}
