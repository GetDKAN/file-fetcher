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
     * @var ProcessorInterface|null
     */
    private ?ProcessorInterface $processor = null;

    /**
     * {@inheritDoc}
     *
     * We override ::get() because it can set values for both newly constructed
     * objects and re-hydrated ones.
     */
    public static function get(string $identifier, $storage, array $config = null)
    {
        $ff = parent::get($identifier, $storage, $config);
        // If we see that a processor is configured, we need to handle some
        // special cases. It might be that the hydrated values for
        // $customProcessorClasses are the same, but the caller could also be
        // telling us to use a different processor than any that were hydrated
        // from storage. We keep the existing ones and prepend the new ones.
        $ff->addProcessors($config);
        $storage->store(json_encode($ff), $identifier);
        return $ff;
    }

    /**
     * Constructor.
     *
     * Constructor is protected. Use static::get() to instantiate a file
     * fetcher.
     *
     * @param string $identifier
     *   File fetcher job identifier.
     * @param $storage
     *   File fetcher job storage object.
     * @param array|NULL $config
     *   Configuration for the file fetcher.
     *
     * @see static::get()
     */
    protected function __construct(string $identifier, $storage, array $config = null)
    {
        parent::__construct($identifier, $storage);

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

    /**
     * Gets the combined custom and default processors list.
     *
     * @return array
     *   The combined custom and default processors list, prioritizing the
     *   custom ones in the order they were defined.
     */
    protected function getProcessors(): array
    {
        $processors = self::getDefaultProcessors();
        // Reverse the array so when we merge it all back together it's in the
        // correct order of precedent.
        foreach (array_reverse($this->customProcessorClasses) as $processorClass) {
            if ($processor = $this->getCustomProcessorInstance($processorClass)) {
                $processors = array_merge([get_class($processor) => $processor], $processors);
            }
        }
        return $processors;
    }

    private static function getDefaultProcessors(): array
    {
        return [
            Local::class => new Local(),
            Remote::class => new Remote(),
        ];
    }

    /**
     * Get the processor used by this file fetcher object.
     *
     * @return ProcessorInterface|null
     *   A processor object, determined by configuration, or NULL if none is
     *   suitable.
     */
    protected function getProcessor(): ?ProcessorInterface
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
     *
     * @see self::addProcessors()
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
     * Add configured processors to the ones already set in the object.
     *
     * Existing custom processor classes will be preserved, but any present in
     * the new config will be prioritized.
     *
     * @param array $config
     *   Configuration array, as passed to __construct() or Job::get(). Should
     *   contain an array of processor class names under the key 'processors'.
     *
     * @see self::setProcessors()
     */
    protected function addProcessors(array $config): void
    {
        // If we have config, and we don't have custom classes already, do the
        // easy thing.
        if (($config['processors'] ?? false) && empty($this->customProcessorClasses)) {
            $this->setProcessors($config);
            return;
        }
        if ($config_processors = $config['processors'] ?? false) {
            $this->processor = null;
            // Unset the configured processors from customProcessorClasses.
            $this->unsetDuplicateCustomProcessorClasses($config_processors);
            // Merge in the configuration.
            $this->customProcessorClasses = array_merge(
                $config_processors,
                $this->customProcessorClasses
            );
        }
    }

    /**
     * Unset items in $this->customProcessorClasses present in the given array.
     *
     * @param array $processors
     *   Processor class names to be removed from the list of custom processor
     *   classes.
     */
    private function unsetDuplicateCustomProcessorClasses(array $processors):void
    {
        foreach ($processors as $processor) {
            // Use array_keys() with its search parameter.
            foreach (array_keys($this->customProcessorClasses, $processor) as $existing) {
                unset($this->customProcessorClasses[$existing]);
            }
        }
    }

    /**
     * Get an instance of the given custom processor class.
     *
     * @param $processorClass
     *   Processor class name.
     *
     * @return ProcessorInterface|null
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
            ['processor']
        );
    }
}
