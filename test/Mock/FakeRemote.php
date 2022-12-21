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
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], 'Hello, World'),
        ]);
        return $mock;
    }

    protected function getFileSize($path): int
    {
        return 10;
    }
}
