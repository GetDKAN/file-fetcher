<?php

namespace FileFetcherTests\Processor;

use FileFetcher\PhpFunctionsBridge;
use FileFetcher\Processor\Local;
use MockChain\Chain;
use PHPUnit\Framework\TestCase;

class LocalTest extends TestCase
{
    public function test()
    {
        $processor = new Local();
        $state = ['source' => 'blah'];
        $this->assertFalse(
            $processor->isServerCompatible($state)
        );
    }
}
