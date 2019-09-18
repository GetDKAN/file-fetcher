<?php

namespace FileFetcher;

use FileFetcher\Processor\Local;
use FileFetcher\Processor\ProcessorInterface;
use FileFetcher\Processor\Remote;
use FileFetcher\Processor\LastResort;
use FileFetcher\Processor\Soda;
use Procrastinator\Job\Job;
use Procrastinator\Result;

class FileFetcher extends Job
{
    private $processors = [];

    public function __construct($filePath, $temporaryDirectory = "/tmp")
    {
        parent::__construct();
        $this->processors = self::getProcessors();

        $state = [
            'source' => $filePath,
            'total_bytes' => 0,
            'total_bytes_copied' => 0,
            'temporary' => false,
            'destination' => $filePath,
            'temporary_directory' => $temporaryDirectory,
        ];

        foreach ($this->processors as $processor) {
            if ($processor->isServerCompatible($state)) {
                $state['processor'] = get_class($processor);
                $state = $processor->setupState($state);
                break;
            }
        }

        $this->setState($state);
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
        $processors[Soda::class] = new Soda();
        $processors[LastResort::class] =  new LastResort();
        return $processors;
    }

    private function getProcessor(): ProcessorInterface
    {
        return $this->processors[$this->getStateProperty('processor')];
    }

    private function setState($state)
    {
        $this->getResult()->setData(json_encode($state));
    }

    public function jsonSerialize()
    {
        $object = parent::jsonSerialize();

        $object->processor = get_class($this->getProcessor());

        return $object;
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

        $p = $reflector->getProperty("processors");
        $p->setAccessible(true);
        $p->setValue($object, self::getProcessors());

        return $object;
    }
}
