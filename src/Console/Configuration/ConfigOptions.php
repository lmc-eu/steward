<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Configuration;

/**
 * @codeCoverageIgnore
 */
final class ConfigOptions
{
    public const CAPABILITIES_RESOLVER = 'capabilities_resolver';
    public const TESTS_DIR = 'tests_dir';
    public const LOGS_DIR = 'logs_dir';

    private function __construct()
    {
    }
}
