<?php

namespace FileFetcher;

use Procrastinator\Job\Job;

class FileFetcher extends Job {

  public function __construct($filePath)
  {
    parent::__construct();
    $this->getResult()->setData(json_encode((object) ['file_path' => $filePath]));
  }

  protected function runIt()
  {
    $result = $this->getResult();
    $data = json_decode($result->getData());
    $location = $this->fetch($data->file_path);
    $data->location = $location;
    $result->setData(json_encode($data));
    return $result;
  }


  /**
   * Tests if the file want to use is usable attempt to make it usable.
   *
   * @param string $filePath
   *   file.
   *
   * @return string usable file path,
   *
   * @throws \Exception If fails to get a usable file.
   */
  private function fetch($filePath) {
    try {

      // Try to download the file some other way.
      // using this method to allow for custom scheme handlers.
      $source = $this->getFileObject($filePath);

      // Is on local file system.
      if ($source->isFile()) {
        return $filePath;
      }

      $pieces = explode("/", $filePath);
      $file_name = end($pieces);
      $tmpFile = $this->getTemporaryFile($file_name);
      $dest    = $this->getFileObject($tmpFile, 'w');

      $this->fileCopy($source, $dest);

      return $tmpFile;
    } catch (\Exception $e) {
      // Failed to get the file.
      throw new \Exception("Unable to fetch {$filePath}. Reason: " . $e->getMessage(), 0, $e);
    }
  }

  /**
   *
   * @param string $filePath
   * @param string $filePath
   * @return \SplFileObject
   */
  private function getFileObject($filePath, $mode = 'r') {
    return new \SplFileObject($filePath, $mode);
  }

  /**
   *
   * @param \SplFileObject $source
   * @param \SplFileObject $dest
   * @return int
   * @throws RuntimeException If either read or write fails
   */
  private function fileCopy(\SplFileObject $source, \SplFileObject $dest) {

    $total = 0;
    while ($source->valid()) {

      // Read a large enough frame to reduce overheads.
      $read = $source->fread(128 * 1024);

      if (FALSE === $read) {
        throw new \RuntimeException("Failed to read from source " . $source->getPath());
      }

      $bytesWritten = $dest->fwrite($read);

      if ($bytesWritten !== strlen($read)) {
        throw new \RuntimeException("Failed to write to destination " . $dest->getPath());
      }

      $total += $bytesWritten;
    }

    return $total;
  }

  /**
   * Generate a tmp filepath for a given $uuid.
   *
   * @param string $uuid
   *   UUID.
   *
   * @return string
   */
  private function getTemporaryFile(string $filename): string {
    return $this->getTemporaryDirectory() . '/' . $this->sanitizeString($filename);
  }

  /**
   * returns the temporary directory used by drupal.
   *
   * @return string
   */
  private function getTemporaryDirectory() {
    return "/tmp";
  }

  /**
   *
   * @param string $string
   * @return string
   */
  private function sanitizeString($string) {
    return preg_replace('~[^a-z0-9.]+~', '_', strtolower($string));
  }

}
