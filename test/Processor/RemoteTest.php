<?php

namespace FileFetcherTests\Processor;

use Contracts\Mock\Storage\Memory;
use FileFetcher\FileFetcher;
use FileFetcher\PhpFunctionsBridge;
use FileFetcher\Processor\Remote;
use FileFetcherTests\Mock\FakeRemote;
use MockChain\Chain;
use MockChain\Options;
use PHPUnit\Framework\TestCase;
use Procrastinator\Result;

class RemoteTest extends TestCase
{

    public function testCopyAFileWithRemoteProcessor()
    {
        $config = [
            "filePath" => 'http://notreal.blah/notacsv.csv',
            "processors" => [FakeRemote::class]
        ];
        $fetcher = FileFetcher::get("1", new Memory(), $config);

        $fetcher->setTimeLimit(1);

        $result = $fetcher->run();
        $state = $fetcher->getState();

        $this->assertEquals($state['total_bytes_copied'], 10);
        $this->assertEquals($result->getStatus(), Result::DONE);
    }

    /**
     * Test the \FileFetcher\Processor\Remote::isServerCompatible() method.
     */
    public function testIsServerCompatible()
    {
        $processor = new Remote();

        // Ensure isServerCompatible() succeeds when supplied valid sources.
        $this->assertTrue($processor->isServerCompatible(['source' => 'example.org']));
        $this->assertTrue($processor->isServerCompatible(['source' => 'http://example.org']));
        $this->assertTrue($processor->isServerCompatible(['source' => 'https://example.org']));

        // Ensure isServerCompatible() fails when supplied invalid sources.
        $this->assertFalse($processor->isServerCompatible(['source' => 'invalid']));
        $this->assertFalse($processor->isServerCompatible(['source' => 'http://invalid']));
        $this->assertFalse($processor->isServerCompatible(['source' => 'https://invalid']));
        $this->assertFalse($processor->isServerCompatible(['source' => 'ftp://example.org']));
    }
}
