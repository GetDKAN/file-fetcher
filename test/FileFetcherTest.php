<?php

namespace FileFetcherTests;

use Contracts\Mock\Storage\Memory;
use FileFetcher\FileFetcher;
use FileFetcher\Processor\Local;
use FileFetcherTests\Mock\FakeLocal;
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

    public function testSwitchProcessor()
    {
        $file_path = __DIR__ . '/files/tiny.csv';
        $temporary_directory = '/temp/foo';
        // Storage.
        $storage = new Memory();
        // Empty.
        $this->assertCount(0, $storage->retrieveAll());

        // Create a file fetcher. Its state will be stored in the memory
        // storage. Specify a non-standard temp dir so that we can test it.
        $config = [
            'filePath' => $file_path,
            'temporaryDirectory' => $temporary_directory
        ];
        $fetcher = FileFetcher::get('1', $storage, $config);
        // Did the ff get stored?
        $this->assertCount(1, $storage->retrieveAll());
        // Ensure we stored the path and temp dir.
        $this->assertEquals($file_path, $fetcher->getState()['source']);
        $this->assertEquals($temporary_directory, $fetcher->getState()['temporary_directory']);

        // Ensure there's no processor info in the state.
        $this->assertArrayNotHasKey('processor', $fetcher->getState());
        $this->assertArrayNotHasKey('customProcessorClasses', $fetcher->getState());

        // What is the processor object?
        $ref_get_processor = new \ReflectionMethod(
            FileFetcher::class,
            'getProcessor'
        );
        $ref_get_processor->setAccessible(true);
        $this->assertInstanceOf(
            Local::class,
            $ref_get_processor->invoke($fetcher)
        );

        // Retrieve the fetcher again, with config for a different processor.
        $fetcher = null;
        $config = [
            'filePath' => $file_path,
            'processors' => [FakeLocal::class]
        ];
        $fetcher = FileFetcher::get('1', $storage, $config);

        // The temporary directory should be our non-default one, proving that
        // it was retrieved from storage.
        $this->assertEquals($temporary_directory, $fetcher->getState()['temporary_directory']);

        // Processor should be the one we specified in ::get().
        $this->assertInstanceOf(
            FakeLocal::class,
            $ref_get_processor->invoke($fetcher)
        );
    }
}
