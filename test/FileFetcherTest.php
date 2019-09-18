<?php

namespace FileFetcherTest;

use FileFetcher\FileFetcher;
use Procrastinator\Result;

class FileFetcherTest extends \PHPUnit\Framework\TestCase
{
    private $sampleCsvSize = 50;

    public function testRemote()
    {
        $fetcher = new \FileFetcher\FileFetcher(
            "http://samplecsvs.s3.amazonaws.com/Sacramentorealestatetransactions.csv"
        );
        $result = $fetcher->run();
        $data = json_decode($result->getData());
        $filepath = "/tmp/samplecsvs_s3_amazonaws_com_sacramentorealestatetransactions.csv";
        $this->assertEquals($filepath, $data->destination);
        $this->assertTrue($data->temporary);
    }

    public function testLocal()
    {
        $local_file = __DIR__ . "/files/tiny.csv";
        $fetcher = new \FileFetcher\FileFetcher($local_file);
        $fetcher->setTimeLimit(1);
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
            filesize("/tmp/dkan_default_content_files_s3_amazonaws_com_{$this->sampleCsvSize}_mb_sample.csv"),
            $fetcher2->getStateProperty('total_bytes_copied')
        );
        $this->assertEquals($fetcher2->getResult()->getStatus(), \Procrastinator\Result::DONE);
    }

    public function testIncompatibleServer()
    {
        $url = "https://data.medicare.gov/api/views/42wc-33ci/rows.csv?accessType=DOWNLOAD&sorting=true";
        $fetcher = new FileFetcher($url);
        $fetcher->setTimeLimit(1);
        $result = $fetcher->run();
        $this->assertEquals(Result::DONE, $result->getStatus());
        $this->assertEquals(2853, json_decode($result->getData())->total_bytes_copied);
    }

    public function testSodaServer()
    {
        $url = "https://data.medicare.gov/resource/rbry-mqwu.csv";
        $fetcher = new FileFetcher($url);
        $result = $fetcher->run();
        $this->assertEquals(Result::DONE, $result->getStatus());
        $lines = file(json_decode($result->getData())->destination);
        $this->assertEquals(5335, count($lines));
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $files = [
          "/tmp/samplecsvs_s3_amazonaws_com_sacramentorealestatetransactions.csv",
          "/tmp/dkan_default_content_files_s3_amazonaws_com_{$this->sampleCsvSize}_mb_sample.csv",
          "/tmp/data_medicare_gov_api_views_42wc_33ci_rows.csv",
          "/tmp/data_medicare_gov_resource_rbry_mqwu.csv"
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
