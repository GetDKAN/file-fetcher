<?php

namespace FileFetcher\Processor;

use FileFetcher\LastResortException;
use FileFetcher\PhpFunctionsBridgeTrait;
use FileFetcher\TemporaryFilePathFromUrl;
use Procrastinator\Result;

/**
 * Class LastResort
 *
 * The "last resort" processor does a regular copy of a file if non of the safer options were possible. This
 * processor will attempt at getting all of the data in one shot and placing it in a file.
 *
 * @package FileFetcher\Processor
 */
class LastResort implements ProcessorInterface
{
    use TemporaryFilePathFromUrl;
    use PhpFunctionsBridgeTrait;

    /**
     * LastResort constructor.
     */
    public function __construct()
    {
        $this->initializePhpFunctionsBridge();
    }

    public function isServerCompatible(array $state): bool
    {
        return true;
    }

    public function setupState(array $state): array
    {
        $state['total_bytes'] = PHP_INT_MAX;
        $state['temporary'] = true;
        $state['destination'] = $this->getTemporaryFilePath($state);

        return $state;
    }

    public function isTimeLimitIncompatible(): bool
    {
        return true;
    }

    public function copy(array $state, Result $result, int $timeLimit = PHP_INT_MAX): array
    {
        list($from, $to) = $this->validateAndGetInfoFromState($state);

        $bytesToRead = 10 * 1000 * 1000;
        $bytesCopied = 0;

        $fin = $this->ensureExistsForReading($from);
        $fout = $this->ensureCreatingForWriting($to);

        while (!feof($fin)) {
            $bytesCopied += $this->readAndWrite(
                $fin,
                $fout,
                $bytesToRead,
                $state
            );
        }

        $result->setStatus(Result::DONE);
        fclose($fin);
        fclose($fout);
        $state['total_bytes_copied'] = $bytesCopied;
        $state['total_bytes'] = $bytesCopied;

        return ['state' => $state, 'result' => $result];
    }

    private function readAndWrite($fin, $fout, $bytesToRead, $state): int
    {
        list($from, $to) = $this->validateAndGetInfoFromState($state);

        $bytesRead = $this->php->fread($fin, $bytesToRead);
        if ($bytesRead === false) {
            throw new LastResortException("reading from", $from);
        }
        $bytesWritten = fwrite($fout, $bytesRead);
        if ($bytesWritten === false) {
            throw new LastResortException("writing to", $to);
        }
        return $bytesWritten;
    }

    private function validateAndGetInfoFromState($state)
    {
        if (!isset($state['source']) && !isset($state['destination'])) {
            throw new \Exception("Incorrect state missing source, destination, or both.");
        }
        return [$state['source'], $state['destination']];
    }

    /**
     * Ensure the target file can be read from.
     *
     * @param string $from
     *   The target filename.
     *
     * @return false|resource
     * @throws \FileFetcher\LastResortException
     */
    private function ensureExistsForReading(string $from)
    {
        $fin = @$this->php->fopen($from, "rb");
        if ($fin === false) {
            throw new LastResortException("opening", $from);
        }
        return $fin;
    }

    /**
     * Ensure the destination file can be created.
     *
     * @param string $to
     *   The destination filename.
     *
     * @return false|resource
     * @throws \FileFetcher\LastResortException
     */
    private function ensureCreatingForWriting(string $to)
    {
        // Delete destination first to avoid appending if existing.
        $this->deleteFile($to);
        $fout = $this->php->fopen($to, "w");
        if ($fout === false) {
            throw new LastResortException("creating", $to);
        }
        return $fout;
    }

    private function deleteFile($file)
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
