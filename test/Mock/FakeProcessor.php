<?php

namespace FileFetcherTests\Mock;

use FileFetcher\Processor\Local;

class FakeProcessor extends Local
{
    public function isServerCompatible(array $state): bool
    {
        return true;
    }
}
