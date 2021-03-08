<?php declare(strict_types=1);

namespace Lmc\Steward;

use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;

/**
 * Provide access to global configuration (from within the tests).
 * The configuration is immutable - ie. cannot be altered after it is used for the first time.
 *
 * @property-read string $browserName
 * @property-read string $env
 * @property-read string $serverUrl
 * @property-read string $capability
 * @property-read string $capabilitiesResolver
 * @property-read string $logsDir
 * @property-read string $debug
 */
class ConfigProvider
{
    /** @var ConfigProvider */
    private static $instance;

    /** @var array Configuration options and theirs values */
    private $config;
    /** @var array Array of custom configuration options that should be added to the default ones */
    private $customConfigurationOptions = [];

    final protected function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Prevent unserializing the singleton
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('ConfigProvider is a singleton and cannot be unserialized.');
    }

    public function __get(string $name)
    {
        $this->initialize();

        if (isset($this->config[$name])) {
            return $this->config[$name];
        }

        throw new \DomainException(sprintf('Configuration option "%s" was not defined', $name));
    }

    public function __set(string $name, $value): void
    {
        throw new \LogicException('Configuration values are immutable after initialization and cannot be changed');
    }

    public function __unset(string $name): void
    {
        throw new \LogicException('Configuration values are immutable after initialization and cannot be changed');
    }

    public function __isset(string $name): bool
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
    public function setCustomConfigurationOptions(array $customConfigurationOptions): void
    {
        if ($this->config !== null) {
            throw new \RuntimeException(
                'Custom configuration options can be set only before initialization of configuration'
            );
        }

        $this->customConfigurationOptions = $customConfigurationOptions;
    }

    private function initialize(): void
    {
        if ($this->config !== null) {
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
    private function assembleConfigurationOptions(): array
    {
        $defaultConfigurationOptions = [
            'BROWSER_NAME',
            'ENV',
            'SERVER_URL',
            'CAPABILITY',
            'CAPABILITIES_RESOLVER',
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
     * @throws \RuntimeException
     * @return array Option => value option name is converted from CAPS_WITH_UNDERSCORES to camelCase
     */
    private function retrieveConfigurationValues(array $options): array
    {
        $outputValues = [];
        $inflector = InflectorFactory::createForLanguage(Language::ENGLISH)->build();

        foreach ($options as $option) {
            $value = getenv($option);

            // value was not retrieved => fail
            if ($value === false) {
                throw new \RuntimeException(sprintf('%s environment variable must be defined', $option));
            }

            $outputValues[$inflector->camelize(mb_strtolower($option))] = $value;
        }

        return $outputValues;
    }

    /**
     * Prevent cloning of the singleton
     */
    private function __clone()
    {
    }
}
