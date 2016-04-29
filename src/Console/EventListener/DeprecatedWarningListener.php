<?php

namespace Lmc\Steward\Console\EventListener;

use Lmc\Steward\Component\Environment;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Show deprecation warning when run-tests alias is used
 *
 * @codeCoverageIgnore
 */
class DeprecatedWarningListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CommandEvents::RUN_TESTS_INIT => 'onCommandRunTestsInit',
        ];
    }

    /**
     * @param ExtendedConsoleEvent $event
     */
    public function onCommandRunTestsInit(ExtendedConsoleEvent $event)
    {
        $output = $event->getOutput();
        $input = $event->getInput();

        if ($input->getArgument('command') == 'run-tests') {
            $output->writeln(
                '<error>You are using depracated "run-tests" command name, which will be removed in '
                . 'future Steward versions. Use "run" command instead!</>'
            );
        }
    }
}
