<?php

namespace Lmc\Steward;

class ConfigHelper
{
    /**
     * Unset config value from the singleton, so next time the config will be get, it will be recreated from
     * current (and possibly adjusted to our needs) environment variables.
     */
    public static function unsetConfigInstance()
    {
        $configProviderSingleton = ConfigProvider::getInstance();
        $reflection = new \ReflectionClass($configProviderSingleton);
        $instance = $reflection->getProperty('config');
        $instance->setAccessible(true);
        $instance->setValue($configProviderSingleton, null);
        $instance->setAccessible(false);
    }

    /**
     * Get minimal array of required environment config options
     * @return array
     */
    public static function getDummyConfig()
    {
        return [
            'BROWSER_NAME' => 'firefox',
            'ENV' => 'testing',
            'SERVER_URL' => 'http://server.tld:4444',
            'CAPABILITY' => '', // intentionally empty, used by ConfigProviderTest::testShouldDetectEmptyConfigOption
            'CAPABILITIES_RESOLVER' => '',
            'FIXTURES_DIR' => __DIR__,
            'LOGS_DIR' => __DIR__,
            'DEBUG' => 0,
        ];
    }

    /**
     * Set environment variables from given array
     * @param array $variables
     */
    public static function setEnvironmentVariables(array $variables)
    {
        foreach ($variables as $key => $value) {
            putenv($key . '=' . $value);
        }
    }
}
