<?php

namespace Lmc\Steward\Console\EventListener;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds option to use Xdebug remote debugger on run testcases (so you can add breakpoints, step the tests etc.).
 *
 * @see https://github.com/lmc-eu/steward/wiki/Debugging-Selenium-tests-with-Steward
 */
class XdebugListener implements EventSubscriberInterface
{
    const OPTION_XDEBUG = 'xdebug';
    const DEFAULT_VALUE = 'phpstorm';
    const DOCS_URL = 'https://github.com/lmc-eu/steward/wiki/Debugging-Selenium-tests-with-Steward';

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
            self::OPTION_XDEBUG,
            null,
            InputOption::VALUE_OPTIONAL,
            'Start Xdebug debugger on tests; use given IDE key. Default value is used only if empty option is passed.',
            self::DEFAULT_VALUE
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

        $this->xdebugIdeKey = $this->getIdeKeyFromInputOption($input);

        if ($this->xdebugIdeKey === null) {
            return;
        }

        if (!extension_loaded('xdebug')) {
            throw new \RuntimeException(
                sprintf(
                    'Extension Xdebug is not loaded or installed. See %s for help and more information.',
                    self::DOCS_URL
                )
            );
        }

        if (!ini_get('xdebug.remote_enable')) {
            throw new \RuntimeException(
                sprintf(
                    'The xdebug.remote_enable directive must be set to true to enable remote debugging. '
                    . 'See %s for help and more information.',
                    self::DOCS_URL
                )
            );
        }

        $output->writeln(
            sprintf('Xdebug remote debugging initialized with IDE key: %s', $this->xdebugIdeKey),
            OutputInterface::VERBOSITY_DEBUG
        );
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

    /**
     * If --xdebug option was not passed at all, return null to not activate the feature.
     * If the option was used without a value, use the default value.
     * If the option was passed with custom value, use this value.
     *
     * @param InputInterface $input
     * @return string|null
     */
    protected function getIdeKeyFromInputOption(InputInterface $input)
    {
        $unparsedOptionValue = $input->getParameterOption('--' . self::OPTION_XDEBUG);

        if ($unparsedOptionValue === null) { // option was passed without value, use the default
            return self::DEFAULT_VALUE;
        } elseif ($unparsedOptionValue !== false) { // option was passed with value, use the value
            return $input->getOption(self::OPTION_XDEBUG);
        }

        return null;
    }
}
