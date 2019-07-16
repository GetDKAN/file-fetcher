<?php


class FileFetcherTest extends \PHPUnit\Framework\TestCase
{

  public function testRemote() {
    // https://drive.google.com/uc?export=download&confirm=-NkI&id=1-9N00dZkOipIAkXMl2D0cdWaVlqfF0E5
    $fetcher = new \FileFetcher\FileFetcher("http://samplecsvs.s3.amazonaws.com/Sacramentorealestatetransactions.csv");
    $result = $fetcher->run();
    $data = json_decode($result->getData());
    $this->assertEquals("/tmp/sacramentorealestatetransactions.csv", $data->destination);
    $this->assertTrue($data->temporary);
  }

  public function testLocal() {
    $local_file = __DIR__ . "/files/tiny.csv";
    $fetcher = new \FileFetcher\FileFetcher($local_file);
    $result = $fetcher->run();
    $data = json_decode($result->getData());
    $this->assertEquals($local_file, $data->destination);
    $this->assertFalse($data->temporary);
  }

  public function testTimeOut() {
    $fetcher = new \FileFetcher\FileFetcher("https://dkan-default-content-files.s3.amazonaws.com/5_mb_sample.csv");
    $file_size = $fetcher->getStateProperty('total_bytes');
    $this->assertLessThan($file_size, $fetcher->getStateProperty('total_bytes_copied'));

    $fetcher->setTimeLimit(1);
    $fetcher->run();
    $this->assertLessThan($file_size, $fetcher->getStateProperty('total_bytes_copied'));
    $this->assertGreaterThan(0, $fetcher->getStateProperty('total_bytes_copied'));
    $this->assertEquals($fetcher->getResult()->getStatus(), \Procrastinator\Result::STOPPED);

    $fetcher->setTimeLimit(PHP_INT_MAX);
    $fetcher->run();
    $this->assertEquals($file_size, $fetcher->getStateProperty('total_bytes_copied'));
    $this->assertEquals(filesize("/tmp/5_mb_sample.csv"), $fetcher->getStateProperty('total_bytes_copied'));
    $this->assertEquals($fetcher->getResult()->getStatus(), \Procrastinator\Result::DONE);
  }

  public function tearDown(): void
  {
    parent::tearDown();
    $files = [
      "/tmp/5_mb_sample.csv",
      "/tmp/sacramentorealestatetransactions.csv"
    ];

    foreach ($files as $file) {
      if (file_exists($file)) {
        unlink($file);
      }
    }
  }
}