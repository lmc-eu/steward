<?php

namespace Lmc\Steward\FunctionalTests\Fixtures\EventListener;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adjust run command execution to make PHPUnit processes using custom configuration xml.
 */
class OverwritePhpunitXmlListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CommandEvents::RUN_TESTS_PROCESS => 'onCommandRunTestsProcess',
        ];
    }

    /**
     * Set custom phpunit.xml to override the default one
     * @param RunTestsProcessEvent $event
     */
    public function onCommandRunTestsProcess(RunTestsProcessEvent $event)
    {
        $args = $event->getArgs();

        // search if configuration file option was already set and unset it if so
        foreach ($args as $argKey => $argValue) {
            if (mb_strpos($argValue, '--configuration=') === 0) {
                unset($args[$argKey]);
                break;
            }
        }

        // add custom path
        $args[] = '--configuration=' . realpath(__DIR__ . '/../phpunit.xml');

        $event->setArgs($args);
    }
}
