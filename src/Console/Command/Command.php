<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\Application;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Configuration\ConfigFileReader;
use Lmc\Steward\Console\Configuration\ConfigResolver;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Console\Style\StewardStyle;
use Lmc\Steward\Exception\CommandException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Base class for all Steward commands.
 * It requires EventDispatcher right in the constructor, to make the commands able to dispatch events.
 */
class Command extends \Symfony\Component\Console\Command\Command
{
    /** @var EventDispatcher */
    protected $dispatcher;
    /** @var StewardStyle */
    protected $io;
    /** @var array */
    protected $config = [];

    public function __construct(EventDispatcher $dispatcher, string $name = null)
    {
        if (!defined('STEWARD_BASE_DIR')) {
            throw new CommandException('The STEWARD_BASE_DIR constant is not defined');
        }

        $this->dispatcher = $dispatcher;

        parent::__construct($name);
    }

    public function getDispatcher(): EventDispatcher
    {
        return $this->dispatcher;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->getDispatcher()->dispatch(
            new ExtendedConsoleEvent($this, $input, $output),
            CommandEvents::PRE_INITIALIZE
        );

        $this->io = new StewardStyle($input, $output);
        $this->config = $this->resolveConfiguration($input);

        parent::initialize($input, $output);
    }

    protected function resolveConfiguration(InputInterface $input): array
    {
        $configFileReader = new ConfigFileReader();

        $configFilePath = $configFileReader->resolvePathToConfigFile(
            $input->getOption(Application::OPTION_CONFIGURATION)
        );

        $configFileValues = [];
        if (!empty($configFilePath)) {
            $configFileValues = $configFileReader->readConfigFile($configFilePath);
        }

        $configResolver = new ConfigResolver(new OptionsResolver(), $this->getDefinition());

        return $configResolver->resolve($input, $configFileValues);
    }
}
