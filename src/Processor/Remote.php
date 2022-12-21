<?php

namespace FileFetcher\Processor;

use GuzzleHttp\Client;
use Procrastinator\Result;

class Remote extends ProcessorBase implements ProcessorInterface
{
    protected const HTTP_URL_REGEX = '%^(?:https?://)?(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-' .
        '\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:' .
        '\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%iu';

    public function isServerCompatible(array $state): bool
    {
        return preg_match(self::HTTP_URL_REGEX, $state['source']) === 1;
    }

    public function setupState(array $state): array
    {
        $state['destination'] = $this->getTemporaryFilePath($state);
        $state['temporary'] = true;
        $state['total_bytes'] = 0;

        if (file_exists($state['destination'])) {
            $state['total_bytes_copied'] = $this->getFilesize($state['destination']);
        }

        return $state;
    }

    public function copy(array $state, Result $result, int $timeLimit = PHP_INT_MAX): array
    {
        $client = $this->getClient();
        try {
            $fout = fopen($state['destination'], "w");
            $client->get($state['source'], ['sink' => $fout]);
            $result->setStatus(Result::DONE);
        } catch (\Exception $e) {
            $result->setStatus(Result::ERROR);
            $result->setError($e->getMessage());
        }

        $state['total_bytes_copied'] = $state['total_bytes'] = $this->getFilesize($state['destination']);
        return ['state' => $state, 'result' => $result];
    }

    protected function getFileSize($path): int
    {
        clearstatcache();
        return filesize($path);
    }

    protected function getClient(): Client
    {
        return new Client();
    }
}
