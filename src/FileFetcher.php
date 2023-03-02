<?php

namespace FileFetcher;

use FileFetcher\Processor\Local;
use FileFetcher\Processor\ProcessorInterface;
use FileFetcher\Processor\Remote;
use Procrastinator\Job\AbstractPersistentJob;

/**
 * @details
 * These can be utilized to make a local copy of a remote file aka fetch a file.
 *
 * ### Basic Usage:
 * @snippet test/FileFetcherTest.php Basic Usage
 */
class FileFetcher extends AbstractPersistentJob
{

    private array $customProcessorClasses = [];

    /**
     * Constructor.
     */
    protected function __construct(string $identifier, $storage, array $config = null)
    {
        parent::__construct($identifier, $storage, $config);

        $this->setProcessors($config);

        $config = $this->validateConfig($config);

        // [State]

        $state = [
            'source' => $config['filePath'],
            'total_bytes' => 0,
            'total_bytes_copied' => 0,
            'temporary' => false,
            'keep_original_filename' => $config['keep_original_filename'] ?? false,
            'destination' => $config['filePath'],
            'temporary_directory' => $config['temporaryDirectory'],
        ];

        // [State]

        foreach ($this->getProcessors() as $processor) {
            if ($processor->isServerCompatible($state)) {
                $state['processor'] = get_class($processor);
                break;
            }
        }

        $this->getResult()->setData(json_encode($state));
    }

    public function setTimeLimit(int $seconds): bool
    {
        if ($this->getProcessor()->isTimeLimitIncompatible()) {
            return false;
        }
        return parent::setTimeLimit($seconds);
    }

    protected function runIt()
    {
        $state = $this->getProcessor()->setupState($this->getState());
        $this->getResult()->setData(json_encode($state));
        $info = $this->getProcessor()->copy($this->getState(), $this->getResult(), $this->getTimeLimit());
        $this->setState($info['state']);
        return $info['result'];
    }

    private function getProcessors()
    {
        $processors = self::getDefaultProcessors();
        foreach ($this->customProcessorClasses as $processorClass) {
            if ($processor = $this->getCustomProcessorInstance($processorClass)) {
                $processors = array_merge([get_class($processor) => $processor], $processors);
            }
        }
        return $processors;
    }

    private static function getDefaultProcessors()
    {
        $processors = [];
        $processors[Local::class] = new Local();
        $processors[Remote::class] = new Remote();
        return $processors;
    }

    private function getProcessor(): ProcessorInterface
    {
        return $this->getProcessors()[$this->getStateProperty('processor')];
    }

    private function validateConfig($config): array
    {
        if (!is_array($config)) {
            throw new \Exception("Constructor missing expected config filePath.");
        }
        if (!isset($config['temporaryDirectory'])) {
            $config['temporaryDirectory'] = "/tmp";
        }
        if (!isset($config['filePath'])) {
            throw new \Exception("Constructor missing expected config filePath.");
        }
        return $config;
    }

    private function setProcessors($config)
    {
        if (!isset($config['processors'])) {
            return;
        }

        if (!is_array($config['processors'])) {
            return;
        }

        $this->customProcessorClasses = $config['processors'];
    }

    private function getCustomProcessorInstance($processorClass)
    {
        if (!class_exists($processorClass)) {
            return;
        }

        $classes = class_implements($processorClass);
        if (!in_array(ProcessorInterface::class, $classes)) {
            return;
        }

        return new $processorClass();
    }
}
