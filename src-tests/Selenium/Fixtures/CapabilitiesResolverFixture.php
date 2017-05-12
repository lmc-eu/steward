<?php

namespace Lmc\Steward\Selenium\Fixtures;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\CustomCapabilitiesResolverInterface;
use Lmc\Steward\Test\AbstractTestCase;

class CapabilitiesResolverFixture implements CustomCapabilitiesResolverInterface
{
    const CUSTOM_DESIRED_CAPABILITY = 'customDesiredCapability';
    const CUSTOM_REQUIRED_CAPABILITY = 'customRequiredCapability';

    public function __construct(ConfigProvider $config)
    {
    }

    public function resolveDesiredCapabilities(AbstractTestCase $test, DesiredCapabilities $capabilities)
    {
        $capabilities->setCapability('customDesiredCapability', true);

        return $capabilities;
    }

    public function resolveRequiredCapabilities(AbstractTestCase $test, DesiredCapabilities $capabilities)
    {
        $capabilities->setCapability('customRequiredCapability', true);

        return $capabilities;
    }
}
