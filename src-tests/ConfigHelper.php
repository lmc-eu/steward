<?php declare(strict_types=1);

namespace Lmc\Steward;

class ConfigHelper
{
    /**
     * Unset config value from the singleton, so next time the config will be get, it will be recreated from
     * current (and possibly adjusted to our needs) environment variables.
     */
    public static function unsetConfigInstance(): void
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
     */
    public static function getDummyConfig(): array
    {
        return [
            'BROWSER_NAME' => 'firefox',
            'ENV' => 'testing',
            'SERVER_URL' => 'http://server.tld:4444',
            'CAPABILITY' => '', // intentionally empty, used by ConfigProviderTest::testShouldDetectEmptyConfigOption
            'CAPABILITIES_RESOLVER' => '',
            'LOGS_DIR' => __DIR__,
            'DEBUG' => 0,
        ];
    }

    /**
     * Set environment variables from given array
     */
    public static function setEnvironmentVariables(array $variables): void
    {
        foreach ($variables as $key => $value) {
            putenv($key . '=' . $value);
        }
    }
}
