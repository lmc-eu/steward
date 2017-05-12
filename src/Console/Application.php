<?php

namespace Lmc\Steward\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputOption;

class Application extends BaseApplication
{
    const OPTION_CONFIGURATION = 'configuration';

    protected function getDefaultInputDefinition()
    {
        $inputDefinition = parent::getDefaultInputDefinition();

        $inputDefinition->addOption(
            new InputOption(
                self::OPTION_CONFIGURATION,
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to custom configuration file'
            )
        );

        return $inputDefinition;
    }
}
