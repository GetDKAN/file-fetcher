<?php

namespace FileFetcherTests;

use Contracts\Mock\Storage\Memory;
use FileFetcher\FileFetcher;
use FileFetcher\Processor\Local;
use FileFetcherTests\Mock\FakeLocal;
use FileFetcherTests\Mock\FakeProcessor;
use FileFetcherTests\Mock\FakeRemote;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FileFetcher\FileFetcher
 * @coversDefaultClass \FileFetcher\FileFetcher
 */
class FileFetcherTest extends TestCase
{

    public function testCopyALocalFile(): void
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

    public function testKeepOriginalFilename(): void
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

    public function testConfigValidationErrorConfigurationMissing(): void
    {
        $this->expectExceptionMessage('Constructor missing expected config filePath.');
        FileFetcher::get(
            "2",
            new Memory()
        );
    }

    public function testConfigValidationErrorMissingFilePath(): void
    {
        $this->expectExceptionMessage('Constructor missing expected config filePath.');
        FileFetcher::get(
            "2",
            new Memory(),
            []
        );
    }

    public function testCustomProcessorsValidationIsNotAnArray(): void
    {
        FileFetcher::get(
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

    public function testCustomProcessorsValidationNotAClass(): void
    {
        FileFetcher::get(
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

    public function testCustomProcessorsValidationImproperClass(): void
    {
        FileFetcher::get(
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

    public function testSwitchProcessor(): void
    {
        $file_path = __DIR__ . '/files/tiny.csv';
        $temporary_directory = '/temp/foo';
        // Storage.
        $storage = new Memory();
        // Empty.
        $this->assertCount(0, $storage->retrieveAll());

        // Create a file fetcher. Its state will be stored in the memory
        // storage. Specify a non-standard temp dir so that we can test that
        // it was stored.
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

        // What is the processor object?
        $ref_get_processor = new \ReflectionMethod(FileFetcher::class, 'getProcessor');
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

        // Processor should be the one we specified in configuration.
        $this->assertInstanceOf(
            FakeLocal::class,
            $ref_get_processor->invoke($fetcher)
        );

        // Retrieve the fetcher again, this time with a different custom
        // processor.
        $fetcher = FileFetcher::get('1', $storage, [
            'filePath' => $file_path,
            'processors' => [FakeProcessor::class]
        ]);
        // The list of custom processors should include both custom processors
        // we specified.
        $ref_custom_processors = new \ReflectionProperty($fetcher, 'customProcessorClasses');
        $ref_custom_processors->setAccessible(true);
        $custom_processors = $ref_custom_processors->getValue($fetcher);
        $this->assertContains(FakeProcessor::class, $custom_processors);
        $this->assertContains(FakeLocal::class, $custom_processors);

        // Assert our non-standard temp directory again.
        $this->assertEquals($temporary_directory, $fetcher->getState()['temporary_directory']);
        // Processor should be the one we specified in configuration.
        $this->assertInstanceOf(
            FakeProcessor::class,
            $ref_get_processor->invoke($fetcher)
        );
    }

    /**
     * @covers ::addProcessors
     */
    public function testAddProcessors(): void
    {
        $file_path = __DIR__ . '/files/tiny.csv';
        $temporary_directory = '/temp/foo';
        // Storage.
        $storage = new Memory();
        // Empty.
        $this->assertCount(0, $storage->retrieveAll());

        // Get a file fetcher.
        $fetcher = FileFetcher::get('1', $storage, [
            'filePath' => $file_path,
            'temporaryDirectory' => $temporary_directory
        ]);

        // Contains no custom processors.
        $ref_custom_processors = new \ReflectionProperty($fetcher, 'customProcessorClasses');
        $ref_custom_processors->setAccessible(true);
        $this->assertEmpty($ref_custom_processors->getValue($fetcher));

        // Let's add a custom processor.
        $ref_add_processors = new \ReflectionMethod($fetcher, 'addProcessors');
        $ref_add_processors->setAccessible(true);
        $ref_add_processors->invokeArgs($fetcher, [[
            'processors' => [
                FakeProcessor::class,
            ]
        ]]);
        // Our processor is in the custom processor list.
        $this->assertEquals([
            FakeProcessor::class,
        ], $ref_custom_processors->getValue($fetcher));

        // We can keep doing this. Add more and make sure they're in the correct
        // order in the list of custom processors.
        $ref_add_processors->invokeArgs($fetcher, [[
            'processors' => [
                FakeLocal::class,
                FakeRemote::class,
            ]
        ]]);
        // New processors are added to the top of the list.
        $this->assertEquals([
            FakeLocal::class,
            FakeRemote::class,
            FakeProcessor::class,
        ], $ref_custom_processors->getValue($fetcher));

        // Re-add FakeProcessor and its position should change in the list.
        $ref_add_processors->invokeArgs($fetcher, [[
            'processors' => [
                FakeProcessor::class,
            ]
        ]]);
        $this->assertEquals([
            FakeProcessor::class,
            FakeLocal::class,
            FakeRemote::class,
        ], $ref_custom_processors->getValue($fetcher));
    }
}
