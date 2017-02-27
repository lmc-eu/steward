<?php

namespace Lmc\Steward\Console\Event;

use Lmc\Steward\Console\Command\Command;
use PHPUnit\Framework\TestCase;

class BasicConsoleEventTest extends TestCase
{
    /** @var Command|\PHPUnit_Framework_MockObject_MockObject */
    protected $commandMock;

    protected function setUp()
    {
        $this->commandMock = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testShouldGetPropertiesPassedInConstructor()
    {
        $event = new BasicConsoleEvent($this->commandMock);
        $this->assertSame($this->commandMock, $event->getCommand());
    }
}
