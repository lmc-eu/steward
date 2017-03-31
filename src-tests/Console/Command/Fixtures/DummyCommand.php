<?php

namespace Lmc\Steward\Console\Command\Fixtures;

use Lmc\Steward\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\VarDumper;

class DummyCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return 0;
    }
}
