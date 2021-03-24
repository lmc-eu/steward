<?php declare(strict_types=1);

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

    public function __construct(OptionsResolver $optionsResolver, InputDefinition $inputDefinition)
    {
        $configurator = new OptionsResolverConfigurator();
        $this->optionsResolver = $configurator->configure($optionsResolver);

        $this->inputDefinition = $inputDefinition;
    }

    public function resolve(InputInterface $input, array $configFileInputOptions): array
    {
        $config = $this->optionsResolver->resolve($configFileInputOptions);

        return $this->setupAndTestDirs($input, $config);
    }

    /**
     * Setup values of configured directories and ensure the directories are accessible.
     *
     * @throws \RuntimeException Thrown when directory is not accessible
     */
    protected function setupAndTestDirs(InputInterface $input, array $config): array
    {
        /** @var InputOption[] $relevantDirOptionsForCommand */
        $relevantDirOptionsForCommand = $this->findRelevantDirOptionsForCurrentCommand();

        foreach ($relevantDirOptionsForCommand as $dirOption) {
            $cliOption = $dirOption->getName();
            $cliValue = $input->getOption($dirOption->getName());
            $wasPassedCliValue = ($input->getParameterOption('--' . $cliOption) !== false);
            $configFileOption = str_replace('-', '_', $cliOption);
            $configFileValue = $config[$configFileOption] ?? null;

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

    private function findRelevantDirOptionsForCurrentCommand(): array
    {
        $relevantDirOptionsForCommand = [];

        foreach ([ConfigOptions::TESTS_DIR, ConfigOptions::LOGS_DIR] as $dirOptionName) {
            $dirOptionName = str_replace('_', '-', $dirOptionName);

            if ($this->inputDefinition->hasOption($dirOptionName)) {
                $relevantDirOptionsForCommand[] = $this->inputDefinition->getOption($dirOptionName);
            }
        }

        return $relevantDirOptionsForCommand;
    }

    private function assertIsReadableDirectory(string $dirPath, InputOption $dirOption): void
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
