<?php

namespace FileFetcherTests\Processor;

use Contracts\Mock\Storage\Memory;
use FileFetcher\FileFetcher;
use FileFetcher\PhpFunctionsBridge;
use FileFetcher\Processor\Remote;
use MockChain\Chain;
use MockChain\Options;
use PHPUnit\Framework\TestCase;
use Procrastinator\Result;

class RemoteTest extends TestCase
{

    public function testCopyAFileWithRemoteProcessor()
    {

        $fetcher = FileFetcher::get(
            "1",
            new Memory(),
            [
                "filePath" => 'http://notreal.blah/notacsv.csv',
                "processors" => [\FileFetcherTests\Mock\FakeRemote::class]
            ]
        );

        $fetcher->setTimeLimit(1);

        $counter = 0;
        do {
            $result = $fetcher->run();
            $counter++;
        } while ($result->getStatus() == Result::STOPPED);

        $state = $fetcher->getState();

        $this->assertTrue(true);

        $abspath = getcwd();
        unlink($abspath . $state['destination']);
    }

    public function testCurlCopy()
    {
        $options = (new Options())
        ->add('curl_exec', "")
        ->index(0);

        $bridge = (new Chain($this))
        ->add(PhpFunctionsBridge::class, '__call', $options)
        ->getMock();

        $processor = new Remote();
        $processor->setPhpFunctionsBridge($bridge);
        $processor->copy([
          'source' => 'hello',
          'destination' => 'goodbye',
          'total_bytes_copied' => 1,
          'total_bytes' => 10,
        ], new Result());
        $this->assertTrue(true);
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
