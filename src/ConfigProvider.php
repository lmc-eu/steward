<?php

namespace Lmc\Steward;

use Doctrine\Common\Inflector\Inflector;
use FlorianWolters\Component\Util\Singleton\SingletonTrait;

/**
 * Provide access to global configuration (from within the tests).
 * The configuration is immutable - ie. cannot be altered after it is used for the first time.
 *
 * @method static ConfigProvider getInstance()
 * @property-read string $browserName
 * @property-read string $env
 * @property-read string $serverUrl
 * @property-read string $capability
 * @property-read string $capabilitiesResolver
 * @property-read string $fixturesDir
 * @property-read string $logsDir
 * @property-read string $debug
 */
class ConfigProvider
{
    use SingletonTrait;

    /** @var array Configuration options and theirs values */
    private $config = null;
    /** @var array Array of custom configuration options that should be added to the default ones */
    private $customConfigurationOptions = [];

    /**
     * @param string $name
     * @return array
     */
    public function __get($name)
    {
        $this->initialize();

        if (isset($this->config[$name])) {
            return $this->config[$name];
        }

        throw new \DomainException(sprintf('Configuration option "%s" was not defined', $name));
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        throw new \LogicException('Configuration values are immutable after initialization and cannot be changed');
    }

    /**
     * @param string $name
     */
    public function __unset($name)
    {
        throw new \LogicException('Configuration values are immutable after initialization and cannot be changed');
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        $this->initialize();

        return isset($this->config[$name]);
    }

    /**
     * Add custom configuration options that should be added to the default ones.
     * Can be set only before initialization, because the configuration is then immutable.
     *
     * Note you cannot override the default configuration options.
     *
     * @param array $customConfigurationOptions Array with values = configuration options (environment variables)
     */
    public function setCustomConfigurationOptions(array $customConfigurationOptions)
    {
        if (!is_null($this->config)) {
            throw new \RuntimeException(
                'Custom configuration options can be set only before initialization of configuration'
            );
        }

        $this->customConfigurationOptions = $customConfigurationOptions;
    }

    /**
     * @deprecated No longer necessary, call the property directly on instance of ConfigProvider
     * @return $this
     */
    public function getConfig()
    {
        $this->initialize();

        return $this;
    }

    private function initialize()
    {
        if (!is_null($this->config)) {
            return;
        }

        $options = $this->assembleConfigurationOptions();

        $this->config = $this->retrieveConfigurationValues($options);
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
            'CAPABILITIES_RESOLVER',
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
