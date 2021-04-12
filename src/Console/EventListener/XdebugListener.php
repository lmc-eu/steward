<?php declare(strict_types=1);

namespace Lmc\Steward\Console\EventListener;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use Lmc\Steward\Exception\RuntimeException;
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
    public const OPTION_XDEBUG = 'xdebug';
    public const DEFAULT_VALUE = 'phpstorm';

    /** @var string */
    protected $xdebugIdeKey;

    public static function getSubscribedEvents(): array
    {
        return [
            CommandEvents::CONFIGURE => 'onCommandConfigure',
            CommandEvents::RUN_TESTS_INIT => 'onCommandRunTestsInit',
            CommandEvents::RUN_TESTS_PROCESS => 'onCommandRunTestsProcess',
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
            self::OPTION_XDEBUG,
            null,
            InputOption::VALUE_OPTIONAL,
            'Start Xdebug debugger on tests. Pass custom IDE key if needed for your IDE settings.',
            ''
        );
    }

    /**
     * Get input option on command initialization
     */
    public function onCommandRunTestsInit(ExtendedConsoleEvent $event): void
    {
        $input = $event->getInput();
        $output = $event->getOutput();

        $this->xdebugIdeKey = $this->getIdeKeyFromInputOption($input);

        if ($this->xdebugIdeKey === null) {
            return;
        }

        if (!extension_loaded('xdebug')) {
            throw RuntimeException::forMissingXdebugExtension();
        }

        $this->assertXdebugConfiguration();

        $output->writeln(
            sprintf('Xdebug remote debugging initialized with IDE key: %s', $this->xdebugIdeKey),
            OutputInterface::VERBOSITY_DEBUG
        );
    }

    /**
     * If the $xdebugIdeKey variable is set, pass it to the process as XDEBUG_CONFIG environment variable
     */
    public function onCommandRunTestsProcess(RunTestsProcessEvent $event): void
    {
        if ($this->xdebugIdeKey) {
            $env = $event->getEnvironmentVars();
            $env['XDEBUG_CONFIG'] = 'idekey=' . $this->xdebugIdeKey;
            $event->setEnvironmentVars($env);
        }
    }

    /**
     * If --xdebug option was not passed at all, return null to not activate the feature.
     * If the option was used without a value, use the default value of idekey.
     * If the option was passed with custom (not empty) value, use this value.
     */
    protected function getIdeKeyFromInputOption(InputInterface $input): ?string
    {
        $optionValue = $input->getOption(self::OPTION_XDEBUG);

        if ($optionValue === null) { // no custom value was passed => use default
            return self::DEFAULT_VALUE;
        }

        if ($optionValue === '') { // empty value was passed => do not enable the feature
            return null;
        }

        return $optionValue;
    }

    private function assertXdebugConfiguration(): void
    {
        if (version_compare(phpversion('xdebug'), '3.0.0', '>=')) {
            if (mb_strpos(ini_get('xdebug.mode'), 'debug') === false) {
                throw RuntimeException::forMissingXdebugConfiguration('"xdebug.mode" must be set to "debug"');
            }
        } else {
            if (!ini_get('xdebug.remote_enable')) {
                throw RuntimeException::forMissingXdebugConfiguration('"xdebug.remote_enable" must be set to true');
            }
        }
    }
}
