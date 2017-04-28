<?php
namespace Lmc\Steward\Selenium;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * Resolve custom WebDriver capabilities to be used for a TestCase
 */
interface CustomCapabilitiesResolverInterface
{
    /**
     * @param ConfigProvider $config
     */
    public function __construct(ConfigProvider $config);

    /**
     * Resolve desired capabilities for given test.
     *
     * @param AbstractTestCase $test
     * @param DesiredCapabilities $capabilities
     * @return DesiredCapabilities
     */
    public function resolveDesiredCapabilities(AbstractTestCase $test, DesiredCapabilities $capabilities);

    /**
     * Resolve required capabilities for given test.
     *
     * Please note "required capabilities" are implemented inconsistently in current WebDriver backends, so you should
     * most probably set only "desired capabilities" until backends conforms the W3C WebDriver spec.
     *
     * @param AbstractTestCase $test
     * @param DesiredCapabilities $capabilities
     * @return DesiredCapabilities
     */
    public function resolveRequiredCapabilities(AbstractTestCase $test, DesiredCapabilities $capabilities);
}
