<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Event;

use Lmc\Steward\Console\Command\Command;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Basic event dispatched from console commands, containing just instance of the Command itself.
 */
class BasicConsoleEvent extends Event
{
    /** @var Command */
    protected $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function getCommand(): Command
    {
        return $this->command;
    }
}
