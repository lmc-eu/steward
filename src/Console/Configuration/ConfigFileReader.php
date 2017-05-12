<?php

namespace Lmc\Steward\Console\Configuration;

use Assert\Assertion;
use Symfony\Component\Yaml\Yaml;

class ConfigFileReader
{
    /**
     * @param string|null $customPath
     * @param string $baseDir
     * @return mixed|string
     */
    public function resolvePathToConfigFile($customPath = null, $baseDir = STEWARD_BASE_DIR)
    {
        if ($customPath !== null) {
            Assertion::file($customPath);

            return $customPath;
        }

        if (is_readable($baseDir . '/steward.yml')) {
            return $baseDir . '/steward.yml';
        }

        if (is_readable($baseDir . '/steward.yml.dist')) {
            return $baseDir . '/steward.yml.dist';
        }

        return '';
    }

    /**
     * @param string $path
     * @return array
     */
    public function readConfigFile($path)
    {
        Assertion::file($path);

        $parsedConfig = Yaml::parse(file_get_contents($path));
        if (is_array($parsedConfig)) {
            return $parsedConfig;
        }

        return [];
    }
}
