<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Configuration;

use Assert\Assertion;
use Symfony\Component\Yaml\Yaml;

class ConfigFileReader
{
    public function resolvePathToConfigFile(?string $customPath, string $baseDir = STEWARD_BASE_DIR): string
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

    public function readConfigFile(string $path): array
    {
        Assertion::file($path);

        $parsedConfig = Yaml::parse(file_get_contents($path));
        if (is_array($parsedConfig)) {
            return $parsedConfig;
        }

        return [];
    }
}
