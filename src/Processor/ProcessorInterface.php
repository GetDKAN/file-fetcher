<?php

namespace FileFetcher\Processor;

use Procrastinator\Result;

/**
 * @details
 * A processor represents a specific way of getting data from a server to
 * produce a file.
 *
 * The FileFetcher::FileFetcher class relies on processors to do the work.
 * In multiple places through out the process of fetching a file, the
 * FileFetcher::FileFetcher asks the processor classes for information or to
 * do some work.
 *
 * The majority of information passed to the Processors from the
 * FileFetcher::FileFetcher comes in an array: $state. The array looks like this:
 *
 * @snippet{lineno} src/FileFetcher.php State
 */
interface ProcessorInterface
{
    /**
     * Whether the state for this file transfer will work with this processor.
     *
     * For instance, a Local processor will check if the source file exists. A
     * Remote processor will check if the source URL is valid.
     *
     * This method is called to determine which configured processor to use to
     * perform the fetch.
     *
     * @param array $state
     *   The file fetcher object's state array.
     *
     * @return bool
     *   True if the processor can be used with the state/configuration. False
     *   otherwise.
     */
    public function isServerCompatible(array $state): bool;

    /**
     * An opportunity to modify the state before attempting to fetch the file.
     *
     * @return array
     *   The appropriately modified state.
     */
    public function setupState(array $state): array;

    /**
     * Copying data from the "source location" into a file.
     *
     * @return array
     *   The return should contain the $state and the $result.
     */
    public function copy(array $state, Result $result, int $timeLimit = PHP_INT_MAX): array;

    /**
     * Whether the processor can deal with time limits.
     *
     * @return bool
     */
    public function isTimeLimitIncompatible(): bool;
}
