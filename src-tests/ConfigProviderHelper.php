<?php declare(strict_types=1);

namespace Lmc\Steward;

/**
 * ConfigProvider with custom predefined options. To be used in test as a ConfigProvider double in classes depending
 * on ConfigProvider. To replace ConfigProvider in classes where the instance is not injected, use ConfigHelper.
 */
class ConfigProviderHelper extends ConfigProvider
{
    protected $config = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function __get(string $name)
    {
        return $this->config[$name];
    }

    public function __isset(string $name): bool
    {
        return isset($this->config[$name]);
    }
}
