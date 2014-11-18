<?php

namespace Lmc\Steward\Console\Command;

use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Base class for all Steward commands.
 * It requires EventDispatcher right in the constructor, to make the commands able to dispatch events.
 */
class Command extends \Symfony\Component\Console\Command\Command
{
    /** @var EventDispatcher */
    protected $dispatcher;

    /**
     * @param EventDispatcher $dispatcher
     * @param string $name
     */
    public function __construct(EventDispatcher $dispatcher, $name = null)
    {
        $this->dispatcher = $dispatcher;

        if (!defined('STEWARD_BASE_DIR')) {
            throw new \RuntimeException('The STEWARD_BASE_DIR constant is not defined');
        }

        parent::__construct($name);
    }

    /**
     * @return EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }
}
