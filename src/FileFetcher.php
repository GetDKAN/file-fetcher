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

    /**
     * Array of processor class names provided by the configuration.
     *
     * @var string[]
     */
    protected array $customProcessorClasses = [];

    /**
     * The processor this file fetcher will use.
     *
     * Stored here so that we don't have to recompute it.
     *
     * @var \FileFetcher\Processor\ProcessorInterface
     */
    private ?ProcessorInterface $processor = null;

    /**
     * Constructor.
     *
     * @param string $identifier
     *   File fetcher job identifier.
     * @param $storage
     *   File fetcher job storage object.
     * @param array|NULL $config
     *   Configuration for the file fetcher.
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

        $this->getResult()->setData(json_encode($state));
    }

    public function setTimeLimit(int $seconds): bool
    {
        if ($this->getProcessor()->isTimeLimitIncompatible()) {
            return false;
        }
        return parent::setTimeLimit($seconds);
    }

    /**
     * {@inheritDoc}
     */
    protected function runIt()
    {
        $state = $this->getProcessor()->setupState($this->getState());
        $this->getResult()->setData(json_encode($state));
        $info = $this->getProcessor()->copy($this->getState(), $this->getResult(), $this->getTimeLimit());
        $this->setState($info['state']);
        return $info['result'];
    }

    protected function getProcessors(): array
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

    /**
     * Get the processor used by this file fetcher object.
     *
     * @return \FileFetcher\Processor\ProcessorInterface
     *   A processor object, determined by configuration.
     */
    protected function getProcessor(): ProcessorInterface
    {
        if ($this->processor) {
            return $this->processor;
        }
        $state = $this->getState();
        foreach ($this->getProcessors() as $processor) {
            if ($processor->isServerCompatible($state)) {
                $this->processor = $processor;
                break;
            }
        }
        return $this->processor;
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

    /**
     * Set custom processors for this file fetcher object.
     *
     * @param $config
     *   Configuration array, as passed to __construct() or Job::get(). Should
     *   contain an array of processor class names under the key 'processors'.
     */
    protected function setProcessors($config)
    {
        $this->processor = null;
        $processors = $config['processors'] ?? [];
        if (!is_array($processors)) {
            $processors = [];
        }
        $this->customProcessorClasses = $processors;
    }

    /**
     * Get an instance of the given custom processor class.
     *
     * @param $processorClass
     *   Processor class name.
     *
     * @return \FileFetcher\Processor\ProcessorInterface|null
     *   An instance of the processor class. If the given class name does not
     *   exist, or does not implement ProcessorInterface, then null is
     *   returned.
     */
    private function getCustomProcessorInstance($processorClass): ?ProcessorInterface
    {
        if (!class_exists($processorClass)) {
            return null;
        }

        $classes = class_implements($processorClass);
        if (!in_array(ProcessorInterface::class, $classes)) {
            return null;
        }

        return new $processorClass();
    }

    /**
     * {@inheritDoc}
     */
    protected function serializeIgnoreProperties(): array
    {
        // Tell our serializer to ignore processor information.
        return array_merge(
            parent::serializeIgnoreProperties(),
            ['processor', 'customProcessorClasses']
        );
    }
}
