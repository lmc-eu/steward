<?php

namespace Lmc\Steward\Console\Configuration;

use Assert\InvalidArgumentException;
use Lmc\Steward\Console\Command\CleanCommand;
use Lmc\Steward\Console\Command\RunCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Lmc\Steward\Console\Configuration\ConfigResolver
 */
class ConfigResolverTest extends \PHPUnit_Framework_TestCase
{
    public function testShouldResolveDefaultGeneralOptions()
    {
        $optionsResolver = new OptionsResolver();
        $inputDefinition = new InputDefinition();
        $configResolver = new ConfigResolver($optionsResolver, $inputDefinition);
        $input = new ArrayInput([]);

        $config = $configResolver->resolve($input, []);

        $this->assertSame(
            [
                ConfigOptions::CAPABILITIES_RESOLVER => '',
            ],
            $config
        );
    }

    public function testShouldResolveDefaultOptionsSpecificForRunCommand()
    {
        $config = $this->resolveRunCommandConfiguration([], []);

        $this->assertStringEndsWith('steward' . DIRECTORY_SEPARATOR . 'tests', $config[ConfigOptions::TESTS_DIR]);
        $this->assertStringEndsWith('steward' . DIRECTORY_SEPARATOR . 'logs', $config[ConfigOptions::LOGS_DIR]);
        $this->assertStringEndsWith('steward' . DIRECTORY_SEPARATOR . 'tests', $config[ConfigOptions::FIXTURES_DIR]);
    }

    public function testShouldResolveDefaultOptionsSpecificForCleanCommand()
    {
        $optionsResolver = new OptionsResolver();
        $cleanCommand = new CleanCommand(new EventDispatcher());
        $configResolver = new ConfigResolver($optionsResolver, $cleanCommand->getDefinition());

        $input = new ArrayInput([]);
        $input->bind($cleanCommand->getDefinition());

        $config = $configResolver->resolve($input, []);

        $this->assertStringEndsWith(
            DIRECTORY_SEPARATOR . 'steward' . DIRECTORY_SEPARATOR . 'logs',
            $config[ConfigOptions::LOGS_DIR]
        );
        $this->assertFalse(isset($config[ConfigOptions::TESTS_DIR]));
        $this->assertFalse(isset($config[ConfigOptions::FIXTURES_DIR]));
    }

    /**
     * @dataProvider provideDirectoryConfiguration
     * @param string $expectedOutputValue
     * @param string $cliValue
     * @param string $configFileValue
     */
    public function testResolveDirectoryValue($expectedOutputValue, $cliValue, $configFileValue)
    {
        $cliInputParams = [];
        if (!empty($cliValue)) {
            $cliInputParams = ['--' . RunCommand::OPTION_LOGS_DIR => $cliValue];
        }

        $configFileOptions = [];
        if (!empty($configFileValue)) {
            $configFileOptions[ConfigOptions::LOGS_DIR] = $configFileValue;
        }

        $config = $this->resolveRunCommandConfiguration($cliInputParams, $configFileOptions);

        $this->assertSame($expectedOutputValue, $config[ConfigOptions::LOGS_DIR]);
    }

    /**
     * @return array[]
     */
    public function provideDirectoryConfiguration()
    {
        return [
            // $expectedOutputValue, $cliValue, $configFileValue
            'no custom CLI nor config file option => use input default' => [
                STEWARD_BASE_DIR . DIRECTORY_SEPARATOR . 'logs',
                '',
                '',
            ],
            'only config file option => use it instead of default' => [
                __DIR__ . '/Fixtures/dir-1',
                '',
                __DIR__ . '/Fixtures/dir-1',
            ],
            'custom CLI option => use it instead of default' => [
                __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'dir-1',
                __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'dir-1',
                '',
            ],
            'both custom CLI option and config file option => use CLI option' => [
                __DIR__ . '/Fixtures/dir-1',
                __DIR__ . '/Fixtures/dir-1',
                __DIR__ . '/Fixtures/dir-2',
            ],
            'config file option with trailing slash => remove the slash' => [
                __DIR__ . '/Fixtures/dir-1',
                '',
                __DIR__ . '/Fixtures/dir-1/',
            ],
            'custom CLI option with traling slash => remove the slash' => [
                __DIR__ . '/Fixtures/dir-1',
                __DIR__ . '/Fixtures/dir-1//',
                '',
            ],
        ];
    }

    public function testShouldThrowExceptionIfCliDefinedDirectoryIsNotReadable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path to directory with tests "/not/existing" does not exist');
        $this->resolveRunCommandConfiguration(['--' . RunCommand::OPTION_TESTS_DIR => '/not/existing'], []);
    }

    public function testShouldThrowExceptionIfConfigFileDefinedDirectoryIsNotReadable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path to directory with tests "/not/existing" does not exist');
        $this->resolveRunCommandConfiguration([], [ConfigOptions::TESTS_DIR => '/not/existing']);
    }

    /**
     * @param array $cliInputParams
     * @param array $configFileInputOptions
     * @return array
     */
    private function resolveRunCommandConfiguration(array $cliInputParams, array $configFileInputOptions)
    {
        $optionsResolver = new OptionsResolver();
        $runCommand = new RunCommand(new EventDispatcher());
        $configResolver = new ConfigResolver($optionsResolver, $runCommand->getDefinition());

        $input = new ArrayInput($cliInputParams);
        $input->bind($runCommand->getDefinition());

        return $configResolver->resolve($input, $configFileInputOptions);
    }
}
