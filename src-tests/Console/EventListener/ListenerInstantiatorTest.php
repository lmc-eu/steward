<?php

namespace Lmc\Steward\Console\EventListener;

use Lmc\Steward\Console\EventListener\ListenerInstantiator;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ListenerInstantiatorTest extends \PHPUnit_Framework_TestCase
{
    /** @var ListenerInstantiator */
    protected $instantiator;

    protected function setUp()
    {
        $this->instantiator = new ListenerInstantiator();
        $this->instantiator->setSearchPathPattern('Fixtures/');
    }

    public function testShouldFindAndAttachListenersToDispatcher()
    {
        $dispatcher = new EventDispatcher();
        // There are no listeners on new dispatcher
        $this->assertEmpty($dispatcher->getListeners());

        $this->instantiator->instantiate($dispatcher, __DIR__);

        $listeners = $dispatcher->getListeners();
        $this->assertNotEmpty($listeners);
        $this->assertArrayHasKey('foo', $listeners);
    }
}
