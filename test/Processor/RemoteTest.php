<?php

namespace FileFetcherTests\Processor;

use Contracts\Mock\Storage\Memory;
use FileFetcher\FileFetcher;
use FileFetcher\Processor\Remote;
use FileFetcherTests\Mock\FakeRemote;
use MockChain\Chain;
use MockChain\Options;
use PHPUnit\Framework\TestCase;
use Procrastinator\Result;

/**
 * @covers \FileFetcher\Processor\Remote
 */
class RemoteTest extends TestCase
{

    public function testCopyAFileWithRemoteProcessor()
    {

        $this->assertNotSame(false, $fetcher = FileFetcher::get(
            "1",
            new Memory(),
            [
                "filePath" => 'http://notreal.blah/notacsv.csv',
                "processors" => [FakeRemote::class]
            ]
        ));

        $fetcher->setTimeLimit(1);

        $result = $fetcher->run();
        $state = $fetcher->getState();

        $this->assertEquals(FakeRemote::class, $state['processor']);
        $this->assertEquals('/tmp/notreal_blah_notacsv.csv', $state['destination']);
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

    public function provideFileSizeHeaders()
    {
        return [
        [1, ['content-length' => 1]],
        [0, ['content-length' => 0]],
        'wrong_type' => [23, ['content-length' => '23']],
        'wrong_type_null' => [0, ['content-length' => null]],
        'no_header' => [0, []],
        ];
    }

    /**
     * @covers \FileFetcher\Processor\Remote::getFileSize()
     * @dataProvider provideFileSizeHeaders
     */
    public function testGetFileSize($expected, $headers)
    {
        $remote = $this->getMockBuilder(Remote::class)
            ->onlyMethods(['getFileSize', 'getHeaders'])
            ->getMock();
        $remote->method('getHeaders')
            ->willReturn($headers);
        $ref_getFileSize = (new \ReflectionClass(Remote::class))
            ->getMethod('getFileSize');
        $ref_getFileSize->setAccessible(true);

        $this->assertSame($expected, $ref_getFileSize->invokeArgs($remote, ['filepath']));
    }
}
