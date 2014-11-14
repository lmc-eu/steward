<?php

namespace Lmc\Steward\Console\Event;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\Event;

class BasicConsoleEvent extends Event
{
    /** @var Command */
    protected $command;

    /**
     * @param Command $command
     */
    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * @return Command A Command instance
     */
    public function getCommand()
    {
        return $this->command;
    }
}
