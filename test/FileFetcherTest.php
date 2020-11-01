<?php

namespace FileFetcherTest;

use Contracts\Mock\Storage\Memory;
use FileFetcher\FileFetcher;
use FileFetcher\Processor\LastResort;
use FileFetcher\Processor\Local;
use FileFetcher\Processor\Remote;
use PHPUnit\Framework\TestCase;
use Procrastinator\Result;

class FileFetcherTest extends TestCase
{
    private $sampleCsvSize = 50;

    public function testRemote()
    {
        // [Basic Usage]

        $fetcher = FileFetcher::get(
            "1",
            new Memory(),
            [
              "filePath" => "http://samplecsvs.s3.amazonaws.com/Sacramentorealestatetransactions.csv",
              "processors" => [Local::class]
            ]
        );

        $result = $fetcher->run();

        // [Basic Usage]

        $data = json_decode($result->getData());
        $filepath = "/tmp/samplecsvs_s3_amazonaws_com_sacramentorealestatetransactions.csv";
        $this->assertEquals($filepath, $data->destination);
        $this->assertTrue($data->temporary);
    }

    public function testKeepOriginalFilename()
    {
        $fetcher = FileFetcher::get(
            "2",
            new Memory(),
            [
                "filePath" => "http://samplecsvs.s3.amazonaws.com/Sacramentorealestatetransactions.csv",
                "processors" => [Remote::class],
                "keep_original_filename" => true,
            ]
        );

        $result = $fetcher->run();

        $data = json_decode($result->getData());
        $filepath = "/tmp/Sacramentorealestatetransactions.csv";
        $this->assertEquals($filepath, $data->destination);
        $this->assertTrue($data->temporary);
    }

    public function testLocal()
    {
        $local_file = __DIR__ . "/files/tiny.csv";

        $config = [
          "filePath" => $local_file,
          "processors" => [TestCase::class]
        ];

        $fetcher = FileFetcher::get(
            "1",
            new Memory(),
            $config
        );

        $fetcher->setTimeLimit(1);
        $result = $fetcher->run();
        $data = json_decode($result->getData());
        $this->assertEquals($local_file, $data->destination);
        $this->assertFalse($fetcher->getStateProperty('keep_original_filename'));
        $this->assertFalse($data->temporary);
    }

    public function testMissingConfigFilePath()
    {
        $this->expectExceptionMessage("Constructor missing expected config filePath.");
        $fetcher = FileFetcher::get(
            "1",
            new Memory()
        );
    }

    public function testTimeOut()
    {
        $store = new Memory();
        $config = [
          "filePath" => "https://dkan-default-content-files.s3.amazonaws.com/files/do_not_delete.csv",
          "processors" => "Bad"
        ];

        $fetcher = FileFetcher::get("1", $store, $config);

        //

        //$this->assertLessThan($file_size, $fetcher->getStateProperty('total_bytes_copied'));

        $fetcher->setTimeLimit(1);
        $fetcher->run();
        $file_size = $fetcher->getStateProperty('total_bytes');
        $this->assertLessThanOrEqual($file_size, $fetcher->getStateProperty('total_bytes_copied'));
        $this->assertGreaterThan(0, $fetcher->getStateProperty('total_bytes_copied'));
        $this->assertEquals($fetcher->getResult()->getStatus(), \Procrastinator\Result::STOPPED);

        $fetcher2 = \FileFetcher\FileFetcher::get("1", $store, $config);

        $fetcher2->setTimeLimit(PHP_INT_MAX);
        $fetcher2->run();
        $this->assertEquals($file_size, $fetcher2->getStateProperty('total_bytes_copied'));

        clearstatcache();
        $actualFileSize = filesize(
            "/tmp/dkan_default_content_files_s3_amazonaws_com_files_do_not_delete.csv"
        );

        $this->assertEquals($actualFileSize, $fetcher2->getStateProperty('total_bytes_copied'));

        $this->assertEquals($fetcher2->getResult()->getStatus(), \Procrastinator\Result::DONE);
    }

    public function testIncompatibleServer()
    {
        $url = "https://data.medicare.gov/api/views/wue8-3vwe/rows.csv?accessType=DOWNLOAD&sorting=true";
        $fetcher = FileFetcher::get(
            "1",
            new Memory(),
            [
              "filePath" => $url,
              "processors" => ["Bad"]
            ]
        );
        $fetcher->setTimeLimit(1);
        $result = $fetcher->run();
        $this->assertEquals(Result::DONE, $result->getStatus());
        $this->assertGreaterThan(0, json_decode($result->getData())->total_bytes_copied);
    }

    public function testLastResortErrorOpening()
    {
        $fetcher = FileFetcher::get(
            "1",
            new Memory(),
            [
                "filePath" => __DIR__ . "/files/non-existent.csv",
                "processors" => [LastResort::class],
            ]
        );
        $fetcher->setTimeLimit(1);
        $result = $fetcher->run();
        $this->assertEquals(Result::ERROR, $result->getStatus());
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $files = [
          "/tmp/samplecsvs_s3_amazonaws_com_sacramentorealestatetransactions.csv",
          "/tmp/Sacramentorealestatetransactions.csv",
          "/tmp/dkan_default_content_files_s3_amazonaws_com_{$this->sampleCsvSize}_mb_sample.csv",
          "/tmp/data_medicare_gov_api_views_42wc_33ci_rows.csv",
          "/tmp/dkan_default_content_files_s3_amazonaws_com_files_do_not_delete.csv"
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
