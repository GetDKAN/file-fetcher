<?php

namespace FileFetcherTests\Processor;

use Contracts\Mock\Storage\Memory;
use Drupal\json_form_widget\ObjectHelper;
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

        $this->assertTrue(true);


        unlink($state['destination']);
    }

    public function testCurlCopy() {
      $options = (new Options())
        ->add('curl_exec', "")
        ->index(0);

      $bridge = (new Chain($this))
        ->add(PhpFunctionsBridge::class, '__call', $options)
        ->getMock();

      $processor = new Remote();
      $processor->setPhpFunctionsBridge($bridge);
      $processor->copy(['source' => 'hello', 'destination' => 'goodbye', 'total_bytes_copied' => 1, 'total_bytes' => 10], new Result());
      $this->assertTrue(true);
    }

  public function testCurlHeaders() {
    $options = (new Options())
      ->add('curl_exec', "Accept-Ranges:TRUE\nContent-Length:10")
      ->index(0);

    $bridge = (new Chain($this))
      ->add(PhpFunctionsBridge::class, '__call', $options)
      ->getMock();

    $processor = new Remote();
    $processor->setPhpFunctionsBridge($bridge);
    $this->assertTrue(
      $processor->isServerCompatible(['source' => 'hello', 'destination' => 'goodbye', 'total_bytes_copied' => 1, 'total_bytes' => 10])
    );
  }

}

class FakeRemote extends Remote {
    protected function getHeaders($url)
    {
        $twoMegaBytes = 20 * 1000 * 1000;
        return Remote::parseHeaders("Accept-Ranges:TRUE\nContent-Length:{$twoMegaBytes}");
    }

    protected function getChunk(string $filePath, int $start, int $end)
    {
        $data = "";
        $numberOfBytes = $end - $start;
        for ($i = 1; $i < $numberOfBytes; $i++) {
            $data .= "A";
        }
        return !empty(trim($data)) ? $data . PHP_EOL : false;
    }

}
