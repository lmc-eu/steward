<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Configuration\ConfigResolver;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Console\Style\StewardStyle;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\Exception\InvalidArgumentException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Yaml;

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

    /**
     * @param EventDispatcher $dispatcher
     * @param string $name
     */
    public function __construct(EventDispatcher $dispatcher, $name = null)
    {
        if (!defined('STEWARD_BASE_DIR')) {
            throw new \RuntimeException('The STEWARD_BASE_DIR constant is not defined');
        }

        $this->dispatcher = $dispatcher;

        parent::__construct($name);
    }

    /**
     * @return EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->getDispatcher()->dispatch(
            CommandEvents::PRE_INITIALIZE,
            new ExtendedConsoleEvent($this, $input, $output)
        );

        $this->io = new StewardStyle($input, $output);
        $this->config = $this->resolveConfiguration($input);

        parent::initialize($input, $output);
    }

    /**
     * @param InputInterface $input
     * @return array
     */
    protected function resolveConfiguration(InputInterface $input)
    {
        $configFileName = '';
        $configFileValues = [];
        if (is_readable(STEWARD_BASE_DIR . '/steward.yml')) {
            $configFileName = STEWARD_BASE_DIR . '/steward.yml';
        } elseif (is_readable(STEWARD_BASE_DIR . '/steward.yml.dist')) {
            $configFileName = STEWARD_BASE_DIR . '/steward.yml.dist';
        }

        if (!empty($configFileName)) {
            $parsedConfig = Yaml::parse(file_get_contents($configFileName));
            if (is_array($parsedConfig)) {
                $configFileValues = $parsedConfig;
            }
        }

        $configResolver = new ConfigResolver(new OptionsResolver(), $this->getDefinition());

        try {
            return $configResolver->resolve($input, $configFileValues);
        } catch (InvalidArgumentException $e) {
            throw new LogicException('Error resolving configuration from file TODO', 0, $e);
        }
    }
}
