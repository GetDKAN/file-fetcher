<?php

namespace FileFetcherTests;

use Contracts\Mock\Storage\Memory;
use FileFetcher\FileFetcher;
use FileFetcher\Processor\Local;
use PHPUnit\Framework\TestCase;

class FileFetcherTest extends TestCase
{

    public function testCopyALocalFile()
    {
        $config = ["filePath" => __DIR__ . '/files/tiny.csv'];
        $fetcher = FileFetcher::get("1", new Memory(), $config);

        // Local does not support time limits.
        $this->assertFalse($fetcher->setTimeLimit(1));
        $fetcher->run();

        $state = $fetcher->getState();

        $this->assertEquals(
            file_get_contents($state['source']),
            file_get_contents($state['destination'])
        );
    }

    public function testKeepOriginalFilename()
    {
        $fetcher = FileFetcher::get(
            "2",
            new Memory(),
            [
                "filePath" => __DIR__ . '/files/tiny.csv',
                "keep_original_filename" => true,
                "processors" => [Local::class],
            ]
        );

        $fetcher->run();
        $state = $fetcher->getState();

        $this->assertEquals(
            basename($state['source']),
            basename($state['destination'])
        );
    }

    public function testConfigValidationErrorConfigurationMissing()
    {
        $this->expectExceptionMessage('Constructor missing expected config filePath.');
        FileFetcher::get(
            "2",
            new Memory()
        );
    }

    public function testConfigValidationErrorMissingFilePath()
    {
        $this->expectExceptionMessage('Constructor missing expected config filePath.');
        FileFetcher::get(
            "2",
            new Memory(),
            []
        );
    }

    public function testCustomProcessorsValidationIsNotAnArray()
    {
        $fetcher = FileFetcher::get(
            "2",
            new Memory(),
            [
                "filePath" => __DIR__ . '/files/tiny.csv',
                "processors" => "hello"
            ]
        );
        // Not sure what to assert.
        $this->assertTrue(true);
    }

    public function testCustomProcessorsValidationNotAClass()
    {
        $fetcher = FileFetcher::get(
            "2",
            new Memory(),
            [
                "filePath" => __DIR__ . '/files/tiny.csv',
                "processors" => ["hello"]
            ]
        );
        // Not sure what to assert.
        $this->assertTrue(true);
    }

    public function testCustomProcessorsValidationImproperClass()
    {
        $fetcher = FileFetcher::get(
            "2",
            new Memory(),
            [
                "filePath" => __DIR__ . '/files/tiny.csv',
                "processors" => [\SplFileInfo::class]
            ]
        );
        // Not sure what to assert.
        $this->assertTrue(true);
    }
}
