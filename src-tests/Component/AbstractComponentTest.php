<?php

namespace Lmc\Steward\Component;

use Lmc\Steward\Component\Fixtures\MockComponent;
use Lmc\Steward\ConfigHelper;
use Lmc\Steward\Test\AbstractTestCase;
use PHPUnit\Framework\TestCase;

class AbstractComponentTest extends TestCase
{
    /** @var AbstractTestCase */
    protected $testCase;

    /** @var MockComponent */
    protected $component;

    protected function setUp()
    {
        $this->testCase = $this->getMockBuilder(AbstractTestCase::class)
            ->setMethods(null)
            ->getMock();

        $this->component = new MockComponent($this->testCase);
    }

    public function testShouldLogStandardOutput()
    {
        $this->expectOutputRegex('/.*\[MockComponent\] Foo bar.*/');

        $this->component->log('Foo %s', 'bar');
    }

    public function testShouldLogWarnings()
    {
        $this->expectOutputRegex('/.*\[WARN\]: \[MockComponent\] Foo bar.*/');
        $this->component->warn('Foo %s', 'bar');
    }

    public function testShouldLogDebugInDebugMode()
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['DEBUG'] = 1;
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();

        $this->expectOutputRegex('/.*\[DEBUG\]: \[MockComponent\] Foo bar.*/');
        $this->component->debug('Foo %s', 'bar');
    }

    public function testShouldNotLogDebugMessagesIfDebugModeIsNotEnabled()
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['DEBUG'] = 0;
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();

        $this->expectOutputRegex('/^((?!\[DEBUG\]).)*$/'); // Output containing [DEBUG] should not be present
        $this->component->debug('Foo %s', 'bar');
    }

    public function testShouldFailIfNotExistingMethodIsCalled()
    {
        $this->expectException(\PHPUnit_Framework_Error::class);
        $this->expectExceptionMessage(
            'Call to undefined method Lmc\Steward\Component\AbstractComponent::notExisting()'
        );

        $this->component->notExisting('Bazbar');
    }
}
