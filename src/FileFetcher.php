<?php

namespace FileFetcher;

use FileFetcher\Processor\Local;
use FileFetcher\Processor\ProcessorInterface;
use FileFetcher\Processor\Remote;
use FileFetcher\Processor\LastResort;
use Procrastinator\Job\AbstractPersistentJob;
use Procrastinator\Result;

/**
 * @details
 * These can be utilized to make a local copy of a remote file aka fetch a file.
 *
 * ### Basic Usage:
 * @snippet test/FileFetcherTest.php Basic Usage
 */
class FileFetcher extends AbstractPersistentJob
{
    private $processors = [];

    /**
     * Constructor.
     */
    protected function __construct(string $identifier, $storage, array $config = null)
    {
        parent::__construct($identifier, $storage, $config);

        if (!isset($config['temporaryDirectory'])) {
            $config['temporaryDirectory'] = "/tmp";
        }

        $this->processors = self::getProcessors();

        // [State]

        $state = [
            'source' => $config['filePath'],
            'total_bytes' => 0,
            'total_bytes_copied' => 0,
            'temporary' => false,
            'destination' => $config['filePath'],
            'temporary_directory' => $config['temporaryDirectory'],
        ];

        // [State]

        foreach ($this->processors as $processor) {
            if ($processor->isServerCompatible($state)) {
                $state['processor'] = get_class($processor);
                $state = $processor->setupState($state);
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
        $info = $this->getProcessor()->copy($this->getState(), $this->getResult(), $this->getTimeLimit());
        $this->setState($info['state']);
        return $info['result'];
    }

    private static function getProcessors()
    {
        $processors = [];
        $processors[Local::class] = new Local();
        $processors[Remote::class] = new Remote();
        $processors[LastResort::class] =  new LastResort();
        return $processors;
    }

    private function getProcessor(): ProcessorInterface
    {
        return $this->processors[$this->getStateProperty('processor')];
    }

    public function jsonSerialize()
    {
        $object = parent::jsonSerialize();

        $object->processor = get_class($this->getProcessor());

        return $object;
    }

    public static function hydrate(string $json, $instance = null)
    {
        $data = json_decode($json);
        $object = $instance;

        $reflector = new \ReflectionClass(self::class);

        if (!$instance) {
            $object = $reflector->newInstanceWithoutConstructor();
        }

        $reflector = new \ReflectionClass($object);

        $p = $reflector->getParentClass()->getParentClass()->getProperty('timeLimit');
        $p->setAccessible(true);
        $p->setValue($object, $data->timeLimit);

        $p = $reflector->getParentClass()->getParentClass()->getProperty('result');
        $p->setAccessible(true);
        $p->setValue($object, Result::hydrate(json_encode($data->result)));

        $p = $reflector->getProperty("processors");
        $p->setAccessible(true);
        $p->setValue($object, self::getProcessors());

        return $object;
    }
}
