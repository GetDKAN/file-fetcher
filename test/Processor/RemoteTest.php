<?php

namespace FileFetcherTests\Processor;

use Contracts\Mock\Storage\Memory;
use FileFetcher\FileFetcher;
use FileFetcher\Processor\Remote;
use FileFetcherTests\Mock\FakeRemote;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Procrastinator\Result;

/**
 * @covers \FileFetcher\Processor\Remote
 */
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

        $this->assertEquals(10, $state['total_bytes_copied']);
        $this->assertEquals(Result::DONE, $result->getStatus());
    }

    public function provideIsServerCompatible(): array
    {
        return [
            [true, 'example.org'],
            [true, 'http://example.org'],
            [true, 'https://example.org'],
            [false, 'invalid'],
            [false, 'http://invalid'],
            [false, 'https://invalid'],
            [false, 'ftp://example.org'],
        ];
    }

    /**
     * Test the \FileFetcher\Processor\Remote::isServerCompatible() method.
     *
     * @dataProvider provideIsServerCompatible
     */
    public function testIsServerCompatible($expected, $source)
    {
        $processor = new Remote();
        $this->assertSame($expected, $processor->isServerCompatible(['source' => $source]));
    }

    public function testCopyException()
    {
        // Ensure the status object contains the message from an exception.
        // We'll use vfsstream to mock a file system with no permissions to
        // throw an error.
        $root = vfsStream::setup('root', 0000);
        $state = ['destination' => $root->url()];

        $remote = new Remote();
        $result = new Result();
        $remote->copy($state, $result);

        $this->assertSame(Result::ERROR, $result->getStatus());
        $this->assertStringContainsString('ailed to open stream', $result->getError());
    }
}
