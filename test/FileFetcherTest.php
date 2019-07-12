<?php


class FileFetcherTest extends \PHPUnit\Framework\TestCase
{
  private $finalFilePaths = [
    "/tmp/sacramentorealestatetransactions.csv"
  ];

  public function testRemote() {
    $fetcher = new \FileFetcher\FileFetcher("http://samplecsvs.s3.amazonaws.com/Sacramentorealestatetransactions.csv");
    $result = $fetcher->run();
    $data = json_decode($result->getData());
    $this->assertEquals($this->finalFilePaths[0], $data->location);
  }

  public function testLocal() {
    $local_file = __DIR__ . "/files/tiny.csv";
    $fetcher = new \FileFetcher\FileFetcher($local_file);
    $result = $fetcher->run();
    $data = json_decode($result->getData());
    $this->assertEquals($local_file, $data->location);
  }

  public function testNotAFile() {
    $fetcher = new \FileFetcher\FileFetcher("http://nope.nxwd/ox.tsv");
    $result = $fetcher->run();
    $this->assertEquals($result->getStatus(), \Procrastinator\Result::ERROR);
    $this->assertEquals($result->getError(), "Unable to fetch http://nope.nxwd/ox.tsv. Reason: SplFileObject::__construct(): php_network_getaddresses: getaddrinfo failed: Name or service not known");
  }

  public function tearDown(): void
  {
    parent::tearDown();
    if (file_exists($this->finalFilePaths[0])) {
      unlink($this->finalFilePaths[0]);
    }
  }
}