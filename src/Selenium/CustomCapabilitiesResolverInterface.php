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

    /**
     * Resolve required capabilities for given test.
     *
     * Please note "required capabilities" are implemented inconsistently in current WebDriver backends, so you should
     * most probably set only "desired capabilities" until backends conforms the W3C WebDriver spec.
     */
    public function resolveRequiredCapabilities(
        AbstractTestCase $test,
        DesiredCapabilities $capabilities
    ): DesiredCapabilities;
}
