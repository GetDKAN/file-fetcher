<?php

namespace FileFetcherTests\Processor;

use FileFetcher\Processor\Local;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FileFetcher\Processor\Local
 * @coversDefaultClass \FileFetcher\Processor\Local
 */
class LocalTest extends TestCase
{

    public function provideSource()
    {
        return [
            'any-normal-file' => ['blah'],
            'no-such-wrapper' => ['s3://foo.bar'],
        ];
    }

    /**
     * @covers ::isServerCompatible
     * @dataProvider provideSource
     */
    public function test($source)
    {
        $processor = new Local();
        $state = ['source' => $source];
        $this->assertFalse(
            $processor->isServerCompatible($state)
        );
    }
}
