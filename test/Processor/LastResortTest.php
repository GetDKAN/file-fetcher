<?php


namespace FileFetcherTests\Processor;

use FileFetcher\PhpFunctionsBridge;
use FileFetcher\Processor\LastResort;
use MockChain\Chain;
use MockChain\Options;
use MockChain\Sequence;
use PHPUnit\Framework\TestCase;
use Procrastinator\Result;

class LastResortTest extends TestCase
{
    function testStateIsValid()
    {
        $this->expectExceptionMessage('Incorrect state missing source, destination, or both.');
        $processor = new LastResort();
        $processor->copy([], new Result());
    }

    function testOpeningSourceException() {
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

    function testOpeningDestinationException() {
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

