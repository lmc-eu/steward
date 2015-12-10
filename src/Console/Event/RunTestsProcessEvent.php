<?php

namespace Lmc\Steward\Console\Event;

use Lmc\Steward\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Event dispatched from `run` command when initializing PHPUnit Processes.
 * It allows you to eg. pass additional arguments to the process.
 */
class RunTestsProcessEvent extends ExtendedConsoleEvent
{
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
        parent::__construct($command, $input, $output);

        $this->processBuilder = $processBuilder;
        $this->args = $args;
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
