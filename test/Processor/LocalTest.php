<?php

namespace FileFetcherTests\Processor;

use FileFetcher\PhpFunctionsBridge;
use FileFetcher\Processor\Local;
use MockChain\Chain;
use PHPUnit\Framework\TestCase;

class LocalTest extends TestCase
{
  public function test() {
    $phpFunctionBridge  = (new Chain($this))
      ->add(PhpFunctionsBridge::class, '__call', true)
      ->getMock();

    $processor = new Local();
    $processor->setPhpFunctionsBridge($phpFunctionBridge);
    $state = ['source' => 'blah'];
    $this->assertFalse(
      $processor->isServerCompatible($state)
    );
  }
}
