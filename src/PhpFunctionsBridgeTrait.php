<?php


namespace FileFetcher;


trait PhpFunctionsBridgeTrait
{
    /**
     * @var PhpFunctionsBridge
     */
    protected $php;

    private function initializePhpFunctionsBridge() {
        $this->php = new PhpFunctionsBridge();
    }

    public function setPhpFunctionsBridge(PhpFunctionsBridge $bridge) {
        $this->php = $bridge;
    }

}