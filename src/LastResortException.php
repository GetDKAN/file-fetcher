<?php

namespace FileFetcher;

use Throwable;

/**
 * Class LastResortException.
 *
 * @package FileFetcher
 */
class LastResortException extends \Exception
{
    public function __construct(string $operation, string $filename)
    {
        $message = sprintf(
            "Error %s file: %s.",
            $operation,
            $filename
        );
        parent::__construct($message, 0, null);
    }
}
