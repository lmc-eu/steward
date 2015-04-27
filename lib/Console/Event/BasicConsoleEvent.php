<?php

namespace Lmc\Steward\Console\Event;

use Lmc\Steward\Console\Command\Command;
use Symfony\Component\EventDispatcher\Event;

/**
 * Basic event dispatched from console commands, containing just instance of the Command itself.
 */
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
