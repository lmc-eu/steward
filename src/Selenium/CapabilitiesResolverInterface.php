<?php
namespace Lmc\Steward\Selenium;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Test\AbstractTestCase;

interface CapabilitiesResolverInterface
{
    /**
     * @param ConfigProvider $config
     */
    public function __construct(ConfigProvider $config);

    /**
     * Resolve capabilities for given test
     *
     * @param AbstractTestCase $test
     * @return DesiredCapabilities
     */
    public function resolveCapabilities(AbstractTestCase $test);
}
