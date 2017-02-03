<?php
namespace Lmc\Steward\Selenium;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * Resolve WebDriver capabilities to be used for a TestCase
 */
interface CapabilitiesResolverInterface
{
    /**
     * @param ConfigProvider $config
     */
    public function __construct(ConfigProvider $config);

    /**
     * Resolve desired capabilities for given test
     *
     * @param AbstractTestCase $test
     * @return DesiredCapabilities
     */
    public function resolveDesiredCapabilities(AbstractTestCase $test);

    /**
     * Resolve required capabilities for given test.
     *
     * Please note "required capabilities" are implemented inconsistently in current WebDriver backends, so you should
     * most probably set only "desired capabilities" unit backends conforms the W3C Webdriver spec.
     *
     * @param AbstractTestCase $test
     * @return DesiredCapabilities
     */
    public function resolveRequiredCapabilities(AbstractTestCase $test);
}
