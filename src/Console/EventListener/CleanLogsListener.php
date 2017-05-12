<?php

namespace Lmc\Steward\Console\EventListener;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invoke clean command before run command starts (unless no-clean option is given)
 */
class CleanLogsListener implements EventSubscriberInterface
{
    const OPTION_NO_CLEAN = 'no-clean';

    public static function getSubscribedEvents()
    {
        return [
            CommandEvents::CONFIGURE => 'onCommandConfigure',
            CommandEvents::PRE_INITIALIZE => 'onCommandPreInitialize',
        ];
    }

    /**
     * Add option to `run` command configuration.
     *
     * @param BasicConsoleEvent $event
     */
    public function onCommandConfigure(BasicConsoleEvent $event)
    {
        if ($event->getCommand()->getName() !== 'run') {
            return;
        }

        $event->getCommand()->addOption(
            self::OPTION_NO_CLEAN,
            null,
            InputOption::VALUE_NONE,
            'Do not clean content of logs directory on startup'
        );
    }

    /**
     * @param ExtendedConsoleEvent $event
     */
    public function onCommandPreInitialize(ExtendedConsoleEvent $event)
    {
        if (!$event->getCommand()->getDefinition()->hasOption(self::OPTION_NO_CLEAN)) {
            return;
        }

        if ($event->getInput()->getOption(self::OPTION_NO_CLEAN)) {
            return;
        }

        $cleanCommand = $event->getCommand()->getApplication()->find('clean');
        $cleanCommand->run(new StringInput(''), $event->getOutput());
    }
}
