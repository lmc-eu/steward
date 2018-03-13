<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Event;

use Lmc\Steward\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Event dispatched from `run` command when initializing PHPUnit Processes.
 * It allows you to eg. pass additional arguments to the process.
 */
class RunTestsProcessEvent extends ExtendedConsoleEvent
{
    /** @var array */
    protected $environmentVars;
    /** @var array */
    protected $args;

    /**
     * @param array $environmentVars Environment variables passed to the process
     * @param array $args Arguments passed to the process
     */
    public function __construct(
        Command $command,
        InputInterface $input,
        OutputInterface $output,
        array $environmentVars,
        array $args
    ) {
        parent::__construct($command, $input, $output);

        $this->environmentVars = $environmentVars;
        $this->args = $args;
    }

    public function getEnvironmentVars(): array
    {
        return $this->environmentVars;
    }

    public function setEnvironmentVars(array $environmentVars): void
    {
        $this->environmentVars = $environmentVars;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function setArgs(array $args): void
    {
        $this->args = $args;
    }
}
