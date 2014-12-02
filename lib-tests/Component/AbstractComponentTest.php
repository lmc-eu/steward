<?php

namespace Lmc\Steward\Component;

use Lmc\Steward\Component\Fixtures\MockComponent;

class AbstractComponentTest extends \PHPUnit_Framework_TestCase
{
    /** @var AbstractTestCase */
    protected $testCase;

    /** @var MockComponent */
    protected $component;

    protected function setUp()
    {
        $this->testCase = $this->getMockBuilder('Lmc\Steward\Test\AbstractTestCase')
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

    /**
     * @expectedException \PHPUnit_Framework_Error
     * @expectedExceptionMessage Call to undefined method Lmc\Steward\Component\AbstractComponent::notExisting()
     */
    public function testShouldFailIfNotExistingMethodIsCalled()
    {
        $this->component->notExisting('Bazbar');
    }
}
