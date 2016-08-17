<?php

namespace Lmc\Steward\Console\Event;

use Lmc\Steward\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtendedConsoleEventTest extends BasicConsoleEventTest
{
    /** @var InputInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $inputMock;
    /** @var OutputInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $outputMock;
    /** @var Command|\PHPUnit_Framework_MockObject_MockObject */
    protected $commandMock;

    protected function setUp()
    {
        $this->commandMock = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->inputMock = $this->getMockBuilder(InputInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->outputMock = $this->getMockBuilder(OutputInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testShouldGetPropertiesPassedInConstructor()
    {
        $event = new ExtendedConsoleEvent($this->commandMock, $this->inputMock, $this->outputMock);
        $this->assertSame($this->commandMock, $event->getCommand());
        $this->assertSame($this->inputMock, $event->getInput());
        $this->assertSame($this->outputMock, $event->getOutput());
    }
}
