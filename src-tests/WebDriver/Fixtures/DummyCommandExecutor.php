<?php

namespace Lmc\Steward\WebDriver\Fixtures;

use Facebook\WebDriver\Remote\WebDriverCommand;
use Facebook\WebDriver\Remote\WebDriverResponse;
use Facebook\WebDriver\WebDriverCommandExecutor;

class DummyCommandExecutor implements WebDriverCommandExecutor
{
    /** @var WebDriverCommand[] Array of executed commands */
    public $executionLog = [];

    /**
     * @param WebDriverCommand $command
     * @return mixed
     */
    public function execute(WebDriverCommand $command)
    {
        $this->executionLog[] = $command;

        return new WebDriverResponse();
    }
}
