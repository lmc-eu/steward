<?php

namespace Lmc\Steward;

/**
 * ConfigProvider with custom predefined options. To be used in test as a ConfigProvider double in classes depending
 * on ConfigProvider. To replace ConfigProvider in classes where the instance is not injected, use ConfigHelper.
 */
class ConfigProviderHelper extends ConfigProvider
{
    protected $config = [];

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function __get($name)
    {
        return $this->config[$name];
    }

    public function __isset($name)
    {
        return isset($this->config[$name]);
    }
}
