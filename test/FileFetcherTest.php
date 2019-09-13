<?php

namespace FileFetcherTest;

class FileFetcherTest extends \PHPUnit\Framework\TestCase
{

    private $sampleCsvSize = 50;

    public function testRemote()
    {
        // [Basic Usage]

        $fetcher = new \FileFetcher\FileFetcher(
            "http://samplecsvs.s3.amazonaws.com/Sacramentorealestatetransactions.csv"
        );
        $result = $fetcher->run();

        // [Basic Usage]

        $data = json_decode($result->getData());
        $this->assertEquals("/tmp/sacramentorealestatetransactions.csv", $data->destination);
        $this->assertTrue($data->temporary);
    }

    public function testLocal()
    {
        $local_file = __DIR__ . "/files/tiny.csv";
        $fetcher = new \FileFetcher\FileFetcher($local_file);
        $result = $fetcher->run();
        $data = json_decode($result->getData());
        $this->assertEquals($local_file, $data->destination);
        $this->assertFalse($data->temporary);
    }

    public function testTimeOut()
    {
        $fetcher = new \FileFetcher\FileFetcher(
            "https://dkan-default-content-files.s3.amazonaws.com/{$this->sampleCsvSize}_mb_sample.csv"
        );
        $file_size = $fetcher->getStateProperty('total_bytes');
        $this->assertLessThan($file_size, $fetcher->getStateProperty('total_bytes_copied'));

        $fetcher->setTimeLimit(1);
        $fetcher->run();
        $this->assertLessThan($file_size, $fetcher->getStateProperty('total_bytes_copied'));
        $this->assertGreaterThan(0, $fetcher->getStateProperty('total_bytes_copied'));
        $this->assertEquals($fetcher->getResult()->getStatus(), \Procrastinator\Result::STOPPED);

        $json = json_encode($fetcher);
        $fetcher2 = \FileFetcher\FileFetcher::hydrate($json);

        $fetcher2->setTimeLimit(PHP_INT_MAX);
        $fetcher2->run();
        $this->assertEquals($file_size, $fetcher2->getStateProperty('total_bytes_copied'));
        $this->assertEquals(
            filesize("/tmp/{$this->sampleCsvSize}_mb_sample.csv"),
            $fetcher2->getStateProperty('total_bytes_copied')
        );
        $this->assertEquals($fetcher2->getResult()->getStatus(), \Procrastinator\Result::DONE);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $files = [
        "/tmp/{$this->sampleCsvSize}_mb_sample.csv",
        "/tmp/sacramentorealestatetransactions.csv"
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
