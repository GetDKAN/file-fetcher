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

        $this->assertNotSame(FALSE, $fetcher = FileFetcher::get(
            "1",
            new Memory(),
            [
                "filePath" => 'http://notreal.blah/notacsv.csv',
                "processors" => [FakeRemote::class]
            ]
        ));

        $fetcher->setTimeLimit(1);

        $counter = 0;
        do {
            $result = $fetcher->run();
            $counter++;
        } while ($result->getStatus() == Result::STOPPED);

        $state = $fetcher->getState();

        $this->assertEquals(FakeRemote::class, $state['processor']);
        $this->assertEquals('/tmp/notreal_blah_notacsv.csv', $state['destination']);
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

    public function provideFileSizeHeaders() {
      return [
        [1, ['content-length' => 1]],
        [0, ['content-length' => 0]],
        'wrong_type' => [23, ['content-length' => '23']],
        'wrong_type_null' => [0, ['content-length' => NULL]],
        'no_header' => [0, []],
        'wrong_case' => [1, ['Content-Length' => 1]],
      ];
    }

    /**
     * @covers \FileFetcher\Processor\Remote::getFileSize()
     * @dataProvider provideFileSizeHeaders
     */
    public function testGetFileSize($expected, $headers) {
      $remote = $this->getMockBuilder(Remote::class)
        ->onlyMethods(['getFileSize', 'getHeaders'])
        ->getMock();
      $remote->method('getHeaders')
        ->willReturn($headers);
      $ref_getFileSize = (new \ReflectionClass(Remote::class))
        ->getMethod('getFileSize');
      $ref_getFileSize->setAccessible(TRUE);

      $this->assertSame($expected, $ref_getFileSize->invokeArgs($remote, ['filepath']));
    }
}
