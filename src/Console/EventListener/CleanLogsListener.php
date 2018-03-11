<?php declare(strict_types=1);

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
    public const OPTION_NO_CLEAN = 'no-clean';

    public static function getSubscribedEvents(): array
    {
        return [
            CommandEvents::CONFIGURE => 'onCommandConfigure',
            CommandEvents::PRE_INITIALIZE => 'onCommandPreInitialize',
        ];
    }

    /**
     * Add option to `run` command configuration.
     */
    public function onCommandConfigure(BasicConsoleEvent $event): void
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

    public function onCommandPreInitialize(ExtendedConsoleEvent $event): void
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
