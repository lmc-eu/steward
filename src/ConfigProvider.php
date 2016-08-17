<?php

namespace Lmc\Steward;

use Configula\Config;
use Doctrine\Common\Inflector\Inflector;
use FlorianWolters\Component\Util\Singleton\SingletonTrait;

/**
 * Provide access to Configuration object instance or create its instance if not yet instantiated.
 *
 * @method static ConfigProvider getInstance()
 * @property-read string browserName
 * @property-read string env
 * @property-read string serverUrl
 * @property-read string capability
 * @property-read string publishResults
 * @property-read string fixturesDir
 * @property-read string logsDir
 * @property-read string debug
 */
class ConfigProvider
{
    use SingletonTrait;

    /** @var Config Config object instance */
    private $config;

    /** @var array Array of custom configuration options that should be added to the default ones */
    private $customConfigurationOptions = [];

    public function __get($name)
    {
        $value = $this->getInstance()->getConfig()->getItem($name, null);

        if ($value !== null) {
            return $value;
        }

        throw new \DomainException(sprintf('Configuration option "%s" was not defined', $name));
    }

    /**
     * Add custom configuration options that should be added to the default ones. Can be set only before first call of
     * getConfig(), as the values must be given to the Config object upon initialization.
     * Note you cannot override the default configuration options.
     *
     * @param array $customConfigurationOptions Array with values = configuration options (environment variables)
     */
    public function setCustomConfigurationOptions(array $customConfigurationOptions)
    {
        if ($this->config) {
            throw new \RuntimeException(
                'Custom configuration options can be set only before the Config object was instantiated'
            );
        }

        $this->customConfigurationOptions = $customConfigurationOptions;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        if (!$this->config) {
            $this->config = new Config(null, $this->prepareConfiguration());
        }

        return $this->config;
    }

    /**
     * @return array
     */
    private function prepareConfiguration()
    {
        $options = $this->assembleConfigurationOptions();

        $configuration = $this->retrieveConfigurationValues($options);

        return $configuration;
    }

    /**
     * Assemble map of configuration options and its default values.
     *
     * @return array Default required configuration options
     */
    private function assembleConfigurationOptions()
    {
        $defaultConfigurationOptions = [
            'BROWSER_NAME',
            'ENV',
            'SERVER_URL',
            'CAPABILITY',
            'PUBLISH_RESULTS',
            'FIXTURES_DIR',
            'LOGS_DIR',
            'DEBUG',
        ];

        // Merge defaults with possible custom options
        $options = array_merge($defaultConfigurationOptions, $this->customConfigurationOptions);

        return $options;
    }

    /**
     * Retrieve given configuration options values from environment. If value is not found, throw and exception.
     *
     * @param array $options
     * @throws \RuntimeException
     * @return array Option => value option name is converted from CAPS_WITH_UNDERSCORES to camelCase
     */
    private function retrieveConfigurationValues($options)
    {
        $outputValues = [];

        foreach ($options as $option) {
            $value = getenv($option);

            // value was not retrieved => fail
            if ($value === false) {
                throw new \RuntimeException(sprintf('%s environment variable must be defined', $option));
            }

            $outputValues[Inflector::camelize(mb_strtolower($option))] = $value;
        }

        return $outputValues;
    }
}
