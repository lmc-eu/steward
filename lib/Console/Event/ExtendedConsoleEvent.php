<?php

namespace Lmc\Steward\Console\Event;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Event;

class ExtendedConsoleEvent extends Event
{
    /** @var Command */
    protected $command;
    /** @var InputInterface */
    protected $input;
    /** @var OutputInterface */
    protected $output;

    /**
     * @param Command $command
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function __construct(Command $command, InputInterface $input, OutputInterface $output)
    {
        $this->command = $command;
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * @return Command A Command instance
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @return InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }
}
