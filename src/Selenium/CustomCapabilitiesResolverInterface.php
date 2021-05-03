<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * Resolve custom WebDriver capabilities to be used for a TestCase
 */
interface CustomCapabilitiesResolverInterface
{
    public function __construct(ConfigProvider $config);

    /**
     * Resolve desired capabilities for given test.
     */
    public function resolveDesiredCapabilities(
        AbstractTestCase $test,
        DesiredCapabilities $capabilities
    ): DesiredCapabilities;
}
