<?php

namespace Lmc\Steward\Console\Event;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class RunTestsProcessEvent extends ExtendedConsoleEvent
{
    /** @var Command */
    protected $command;
    /** @var InputInterface */
    protected $input;
    /** @var OutputInterface */
    protected $output;
    /** @var ProcessBuilder */
    protected $processBuilder;
    /** @var array */
    protected $args;

    /**
     * @param Command $command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param ProcessBuilder $processBuilder
     * @param array $args Arguments passed to the process
     */
    public function __construct(
        Command $command,
        InputInterface $input,
        OutputInterface $output,
        ProcessBuilder $processBuilder,
        array $args
    ) {
        $this->command = $command;
        $this->input = $input;
        $this->output = $output;
        $this->processBuilder = $processBuilder;
        $this->args = $args;
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

    /**
     * @return ProcessBuilder
     */
    public function getProcessBuilder()
    {
        return $this->processBuilder;
    }

    /**
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * Allow to update args array
     * @param array $args
     */
    public function setArgs($args)
    {
        $this->args = $args;
    }
}
