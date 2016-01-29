<?php

namespace Lmc\Steward\Console\EventListener;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds option to use Xdebug remote debugger on run testcases (so you can add breakpoints, step the tests etc.).
 *
 * Remember, you must first install Xdebug and than set it up in php.ini (or xdebug.ini) in a similar way:
 * zend_extension=/path/to/xdebug.so
 * xdebug.remote_enable=1
 * xdebug.remote_handler=dbgp
 * xdebug.remote_mode=req
 * xdebug.remote_host=127.0.0.1
 * xdebug.remote_port=9000
 * (see http://xdebug.org/docs/remote for docs)
 *
 * Your IDE must then listen for incoming Xdebug connections on the same port and use the same IDE key.
 * Then start the `run` command with --xdebug option. For PHPStorm just use the default value ("phpstorm"),
 * for eg. NetBeans you must pass "--xdebug=netbeans". See docs of you IDE for more information.
 *
 */
class XdebugListener implements EventSubscriberInterface
{
    /** @var string */
    protected $xdebugIdeKey;

    public static function getSubscribedEvents()
    {
        return [
            CommandEvents::CONFIGURE => 'onCommandConfigure',
            CommandEvents::RUN_TESTS_INIT => 'onCommandRunTestsInit',
            CommandEvents::RUN_TESTS_PROCESS => 'onCommandRunTestsProcess',
        ];
    }

    /**
     * Add option to `run` command configuration.
     *
     * @param BasicConsoleEvent $event
     */
    public function onCommandConfigure(BasicConsoleEvent $event)
    {
        if ($event->getCommand()->getName() != 'run') {
            return;
        }

        $event->getCommand()->addOption(
            'xdebug',
            null,
            InputOption::VALUE_OPTIONAL,
            'Start Xdebug debugger on tests; use given IDE key. Default value is used only if empty option is passed.',
            'phpstorm'
        );
    }

    /**
     * Get input option on command initialization
     *
     * @param ExtendedConsoleEvent $event
     */
    public function onCommandRunTestsInit(ExtendedConsoleEvent $event)
    {
        $input = $event->getInput();
        $output = $event->getOutput();

        // Use the value of --xdebug only if the option was passed.
        // Don't apply the default if the option was not passed at all.
        if ($input->getParameterOption('--xdebug') !== false) {
            $this->xdebugIdeKey = $input->getOption('xdebug');
        }

        if ($this->xdebugIdeKey) {
            if (!extension_loaded('xdebug')) {
                throw new \RuntimeException('Extension Xdebug is not loaded or installed');
            }

            if (!ini_get('xdebug.remote_enable')) {
                throw new \RuntimeException(
                    'The xdebug.remote_enable directive must be true to enable remote debugging'
                );
            }

            $output->writeln(
                sprintf('Xdebug remote debugging initialized with IDE key: %s', $this->xdebugIdeKey),
                OutputInterface::VERBOSITY_DEBUG
            );
        }
    }

    /**
     * If the $xdebugIdeKey variable is set, pass it to the process as XDEBUG_CONFIG environment variable
     *
     * @param RunTestsProcessEvent $event
     */
    public function onCommandRunTestsProcess(RunTestsProcessEvent $event)
    {
        if ($this->xdebugIdeKey) {
            $event->getProcessBuilder()
                ->setEnv('XDEBUG_CONFIG', 'idekey=' . $this->xdebugIdeKey);
        }
    }
}
