<?php

namespace FileFetcherTests\Mock;

use FileFetcher\Processor\Local;

class FakeLocal extends Local
{
    public function isServerCompatible(array $state): bool
    {
        return true;
    }
}
