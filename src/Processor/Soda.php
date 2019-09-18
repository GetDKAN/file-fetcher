<?php


namespace FileFetcher\Processor;


use FileFetcher\Headers;
use FileFetcher\TemporaryFilePathFromUrl;
use Procrastinator\Result;

class Soda implements ProcessorInterface
{
    use Headers;
    use TemporaryFilePathFromUrl;

    public function isServerCompatible(array $state): bool
    {
        $headers = $this->getHeaders($state['source']);
        if (!isset($headers['X-Socrata-RequestId'])) {
            return false;
        }

        $headers = get_headers($state['source']);
        foreach ($headers as $header) {
            if (substr_count($header, "X-SODA2") > 0) {
                return true;
            }
        }

        return false;
    }

    public function setupState(array $state): array
    {
        $state['destination'] = $this->getTemporaryFilePath($state);
        $state['temporary'] = true;
        $state['total_bytes'] = null;

        if (file_exists($state['destination'])) {
            unlink($state['destination']);
        }

        // Custom state.
        $state['next_offset'] = 0;

        return $state;
    }

    public function copy(array $state, Result $result, int $timeLimit = PHP_INT_MAX): array
    {
        $numberOfRecordsPerRequest = 1000;

        $destinationFile = $state['destination'];
        $total = $state['total_bytes_copied'];

        $expiration = time() + $timeLimit;

        while ($chunk = $this->getChunk($state)) {
            $bytesWritten = $this->createOrAppend($destinationFile, $chunk);

            if ($bytesWritten !== strlen($chunk)) {
                throw new \RuntimeException(
                    "Unable to fetch {$state['source']}. " .
                    " Reason: Failed to write to destination " . $destinationFile,
                    0
                );
            }

            $state['next_offset'] += $numberOfRecordsPerRequest;
            $total += $bytesWritten;
            $state['total_bytes_copied'] = $total;

            $currentTime = time();
            if ($currentTime > $expiration) {
                $result->setStatus(Result::STOPPED);
                return ['state' => $state, 'result' => $result];
            }
        }

        $result->setStatus(Result::DONE);
        return ['state' => $state, 'result' => $result];
    }

    public function isTimeLimitIncompatible(): bool
    {
        return false;
    }

    private function getChunk($state)
    {
        $url = $state['source'];
        $offset = $state['next_offset'];

        $content = file_get_contents($url . '?$offset=' . $offset);
        if ($offset !== 0) {
            $content = preg_replace('/^.+\n/', '', $content);
        }
        return $content;
    }

    private function createOrAppend($filePath, $chunk)
    {
        if (!file_exists($filePath)) {
            $bytesWritten = file_put_contents($filePath, $chunk);
        } else {
            $bytesWritten = file_put_contents($filePath, $chunk, FILE_APPEND);
        }
        return $bytesWritten;
    }
}
