<?php

namespace FileFetcher;

use Procrastinator\Job\Job;
use Procrastinator\Result;

/**
 * @details
 * These can be utilized to make a local copy of a remote file aka fetch a file.
 *
 * ### Basic Usage:
 * @snippet test/FileFetcherTest.php Basic Usage
 */
class FileFetcher extends Job
{
    private $temporaryDirectory;
    private $chunkSizeInBytes = (1024 * 100);

    public function __construct($filePath, $temporaryDirectory = "/tmp")
    {
        parent::__construct();

        $this->temporaryDirectory = $temporaryDirectory;

        $state = [
          'source' => $filePath,
          'total_bytes_copied' => 0
        ];

        $file = new \SplFileObject($filePath);

        $state['temporary'] = !$file->isFile();
        $state['destination'] = $file->isFile() ? $filePath : $this->getTemporaryFilePath($filePath);

        if (!$file->isFile() && $this->serverIsNotCompatible($filePath)) {
            throw new \Exception("The server hosting the file does not support ranged requests.");
        }

        $state['total_bytes'] = $file->isFile() ? $file->getSize() : $this->getRemoteFileSize($filePath);

        if (file_exists($state['destination'])) {
            $state['total_bytes_copied'] = filesize($state['destination']);
        }

        $this->setState($state);
    }

    protected function runIt()
    {
        try {
            $this->copy();
            $result = $this->getResult();
            $result->setStatus(Result::DONE);
        } catch (FileCopyInterruptedException $e) {
            $result = $this->getResult();
            $result->setStatus(Result::STOPPED);
        }

        return $result;
    }

    private function serverIsNotCompatible($url)
    {
        $headers = $this->getHeaders($url);

        if (!isset($headers['Accept-Ranges']) || !isset($headers['Content-Length'])) {
            return true;
        }

        return false;
    }

    private function getRemoteFileSize($url)
    {
        $headers = $this->getHeaders($url);
        return $headers['Content-Length'];
    }

    private function getHeaders($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        $headers = $this->parseHeaders(curl_exec($ch));
        curl_close($ch);
        return $headers;
    }

    private function parseHeaders($string)
    {
        $headers = [];
        $lines = explode(PHP_EOL, $string);
        foreach ($lines as $line) {
            $line = trim($line);
            $parts = explode(":", $line);
            if (count($parts) > 1) {
                $key = array_shift($parts);
                $value = trim(implode(":", $parts));
                $headers[$key] = $value;
            } else {
                if (!empty($value)) {
                    $headers[] = $value;
                }
            }
        }
        return $headers;
    }


  /**
   * Copy the remote file locally.
   */
    private function copy()
    {

        if ($this->getStateProperty('temporary') == false) {
            return;
        }

        $destination_file = $this->getStateProperty('destination');
        $total = $this->getStateProperty('total_bytes_copied');

        $time_limit = ($this->getTimeLimit()) ? time() + $this->getTimeLimit() : time() + PHP_INT_MAX;


        while ($chunk = $this->getChunk()) {
            if (!file_exists($destination_file)) {
                $bytesWritten = file_put_contents($destination_file, $chunk);
            } else {
                $bytesWritten = file_put_contents($destination_file, $chunk, FILE_APPEND);
            }

            if ($bytesWritten !== strlen($chunk)) {
                throw new \RuntimeException(
                    "Unable to fetch {$this->getStateProperty('source')}. " .
                    " Reason: Failed to write to destination " . $destination_file,
                    0
                );
            }

            $total += $bytesWritten;
            $this->setStateProperty('total_bytes_copied', $total);

            if (time() > $time_limit) {
                $this->setStateProperty('total_bytes_copied', $total);
                throw new FileCopyInterruptedException(
                    "Stopped copying file after {$total} bytes. Time limit of " .
                    "{$this->getTimeLimit()} second(s) reached."
                );
            }
        }
    }

    private function getChunk()
    {
        $url = $this->getStateProperty('source');
        $start = $this->getStateProperty('total_bytes_copied');
        $end = $start + $this->chunkSizeInBytes;

        if ($end > $this->getStateProperty('total_bytes')) {
            $end = $this->getStateProperty('total_bytes');
        }

        if ($start == $end) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RANGE, "{$start}-{$end}");
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function getTemporaryFilePath($sourceFileUrl)
    {
        $pieces = explode("/", $sourceFileUrl);
        $file_name = end($pieces);
        return $this->getTemporaryFile($file_name);
    }

  /**
   * Generate a tmp filepath for a given $uuid.
   *
   * @param string $uuid
   *   UUID.
   *
   * @return string
   */
    private function getTemporaryFile(string $filename): string
    {
        return $this->getTemporaryDirectory() . '/' . $this->sanitizeString($filename);
    }

  /**
   * returns the temporary directory used by drupal.
   *
   * @return string
   */
    private function getTemporaryDirectory()
    {
        return $this->temporaryDirectory;
    }

  /**
   *
   * @param string $string
   * @return string
   */
    private function sanitizeString($string)
    {
        return preg_replace('~[^a-z0-9.]+~', '_', strtolower($string));
    }

    private function setState($state)
    {
        $this->getResult()->setData(json_encode($state));
    }

    public function setStateProperty($property, $value)
    {
        $state = $this->getState();
        $state[$property] = $value;
        $this->setState($state);
    }

    public function jsonSerialize()
    {
        return (object) [
          'timeLimit' => $this->getTimeLimit(),
          'result' => $this->getResult(),
          'temporaryDirectory' => $this->temporaryDirectory
        ];
    }

    public static function hydrate($json)
    {
        $data = json_decode($json);

        $reflector = new \ReflectionClass(self::class);
        $object = $reflector->newInstanceWithoutConstructor();

        $reflector = new \ReflectionClass($object);

        $p = $reflector->getParentClass()->getProperty('timeLimit');
        $p->setAccessible(true);
        $p->setValue($object, $data->timeLimit);

        $p = $reflector->getParentClass()->getProperty('result');
        $p->setAccessible(true);
        $p->setValue($object, Result::hydrate(json_encode($data->result)));

        $p = $reflector->getProperty('temporaryDirectory');
        $p->setAccessible(true);
        $p->setValue($object, $data->temporaryDirectory);

        return $object;
    }
}
