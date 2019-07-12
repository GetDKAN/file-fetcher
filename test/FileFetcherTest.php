<?php


class FileFetcherTest extends \PHPUnit\Framework\TestCase
{
  private $finalFilePaths = [
    "/tmp/sacramentorealestatetransactions.csv"
  ];

  public function test() {
    $fetcher = new \FileFetcher\FileFetcher();
    $location = $fetcher->fetch("http://samplecsvs.s3.amazonaws.com/Sacramentorealestatetransactions.csv");
    $this->assertEquals($this->finalFilePaths[0], $location);

    $local_file = __DIR__ . "/files/tiny.csv";
    $location = $fetcher->fetch($local_file);
    $this->assertEquals($local_file, $location);
  }

  public function testNotAFile() {
    $this->expectExceptionMessage("Unable to fetch http://nope.nxwd/ox.tsv. Reason: SplFileObject::__construct(): php_network_getaddresses: getaddrinfo failed: Name or service not known");
    $fetcher = new \FileFetcher\FileFetcher();
    $fetcher->fetch("http://nope.nxwd/ox.tsv");
  }

  public function tearDown(): void
  {
    parent::tearDown();
    if (file_exists($this->finalFilePaths[0])) {
      unlink($this->finalFilePaths[0]);
    }
  }
}