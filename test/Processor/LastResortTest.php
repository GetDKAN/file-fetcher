<?php

namespace FileFetcherTests\Processor;

use Contracts\Mock\Storage\Memory;
use FileFetcher\FileFetcher;
use FileFetcher\PhpFunctionsBridge;
use FileFetcher\Processor\LastResort;
use MockChain\Chain;
use MockChain\Options;
use MockChain\Sequence;
use PHPUnit\Framework\TestCase;
use Procrastinator\Result;

class LastResortTest extends TestCase
{
    public function testCopyALocalFileWithLastResortProcessor()
    {

        $fetcher = FileFetcher::get(
            "1",
            new Memory(),
            [
                "filePath" => __DIR__ . '/../files/tiny.csv',
                "processors" => [LastResort::class]
            ]
        );

        // Last resort does not support time limits.
        $this->assertFalse($fetcher->setTimeLimit(1));

        $fetcher->run();

        $state = $fetcher->getState();

        $this->assertEquals(
            file_get_contents($state['source']),
            file_get_contents($state['destination'])
        );

        unlink($state['destination']);
    }

    public function testStateIsValid()
    {
        $this->expectExceptionMessage('Incorrect state missing source, destination, or both.');
        $processor = new LastResort();
        $processor->copy([], new Result());
    }

    public function testOpeningSourceException()
    {
        $this->expectExceptionMessage('Error opening file: hello.');
        $state = ['source' => 'hello', 'destination' => 'goodbye'];

        $options = (new Options())
            ->add('fopen', false)
            ->index(0);

        $phpFunctionsBridge = (new Chain($this))
            ->add(PhpFunctionsBridge::class, '__call', $options)
            ->getMock();

        $processor = new LastResort();
        $processor->setPhpFunctionsBridge($phpFunctionsBridge);

        $processor->copy($state, new Result());
    }

    public function testOpeningDestinationException()
    {
        $this->expectExceptionMessage('Error creating file: goodbye.');
        $state = ['source' => 'hello', 'destination' => 'goodbye'];

        $sequence = (new Sequence())
            ->add(1)
            ->add(false);

        $options = (new Options())
            ->add('fopen', $sequence)
            ->index(0);

        $phpFunctionsBridge = (new Chain($this))
            ->add(PhpFunctionsBridge::class, '__call', $options)
            ->getMock();

        $processor = new LastResort();
        $processor->setPhpFunctionsBridge($phpFunctionsBridge);

        $processor->copy($state, new Result());
    }
}
