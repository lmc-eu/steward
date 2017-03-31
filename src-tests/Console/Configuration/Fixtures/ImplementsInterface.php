<?php

namespace Lmc\Steward\Console\Configuration\Fixtures;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\CapabilitiesResolverInterface;
use Lmc\Steward\Test\AbstractTestCase;

class ImplementsInterface implements CapabilitiesResolverInterface
{
    public function __construct(ConfigProvider $config)
    {
    }

    public function resolveDesiredCapabilities(AbstractTestCase $test)
    {
        return new DesiredCapabilities();
    }

    public function resolveRequiredCapabilities(AbstractTestCase $test)
    {
        return new DesiredCapabilities();
    }
}
