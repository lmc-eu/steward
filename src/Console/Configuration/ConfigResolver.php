<?php

namespace Lmc\Steward\Console\Configuration;

use Assert\Assert;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Resolve current configuration values based on CLI input and config file
 */
class ConfigResolver
{
    /** @var OptionsResolver */
    private $optionsResolver;
    /** @var InputDefinition */
    private $inputDefinition;

    /**
     * @param OptionsResolver $optionsResolver
     * @param InputDefinition $inputDefinition
     */
    public function __construct(OptionsResolver $optionsResolver, InputDefinition $inputDefinition)
    {
        $configurator = new OptionsResolverConfigurator();
        $this->optionsResolver = $configurator->configure($optionsResolver);

        $this->inputDefinition = $inputDefinition;
    }

    /**
     * @param InputInterface $input
     * @param array $configFileInputOptions
     * @return array
     */
    public function resolve(InputInterface $input, array $configFileInputOptions)
    {
        $config = $this->optionsResolver->resolve($configFileInputOptions);

        $config = $this->setupAndTestDirs($input, $config);

        return $config;
    }

    /**
     * Setup values of configured directories and ensure the directories are accessible.
     *
     * @param InputInterface $input
     * @param array $config Original configuration
     * @throws \RuntimeException Thrown when directory is not accessible
     * @return array Updated configuration
     */
    protected function setupAndTestDirs(InputInterface $input, array $config)
    {
        $relevantDirOptionsForCommand = $this->findRelevantDirOptionsForCurrentCommand();

        /** @var $relevantDirOptionsForCommand InputOption[] */
        foreach ($relevantDirOptionsForCommand as $dirOption) {
            $cliOption = $dirOption->getName();
            $cliValue = $input->getOption($dirOption->getName());
            $wasPassedCliValue = ($input->getParameterOption('--' . $cliOption) !== false);
            $configFileOption = str_replace('-', '_', $cliOption);
            $configFileValue = isset($config[$configFileOption]) ? $config[$configFileOption] : null;

            if ($wasPassedCliValue) { // CLI value has priority when passed
                $currentValue = $cliValue;
            } elseif ($configFileValue !== null) {
                $currentValue = $configFileValue;
            } else { // If not passed and not in config file use the default CLI value
                $currentValue = $cliValue;
            }

            $this->assertIsReadableDirectory($currentValue, $dirOption);

            $currentValue = rtrim($currentValue, '/');

            $config[$configFileOption] = $currentValue;
        }

        return $config;
    }

    /**
     * @return array
     */
    private function findRelevantDirOptionsForCurrentCommand()
    {
        $relevantDirOptionsForCommand = [];

        foreach ([ConfigOptions::TESTS_DIR, ConfigOptions::LOGS_DIR, ConfigOptions::FIXTURES_DIR] as $dirOptionName) {
            $dirOptionName = str_replace('_', '-', $dirOptionName);

            if ($this->inputDefinition->hasOption($dirOptionName)) {
                $relevantDirOptionsForCommand[] = $this->inputDefinition->getOption($dirOptionName);
            }
        }

        return $relevantDirOptionsForCommand;
    }

    /**
     * @param string $dirPath
     * @param InputOption $dirOption
     */
    private function assertIsReadableDirectory($dirPath, InputOption $dirOption)
    {
        $errorMessage = sprintf(
            '%s "%s" does not exist, make sure it is accessible or define your own path using %s option',
            $dirOption->getDescription(),
            $dirPath,
            '--' . $dirOption->getName()
        );

        Assert::that($dirPath, $errorMessage)->directory()->readable();
    }
}
