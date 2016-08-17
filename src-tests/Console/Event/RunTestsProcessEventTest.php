<?php

namespace Lmc\Steward\Console\Event;

use Lmc\Steward\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

class RunTestsProcessEventTest extends ExtendedConsoleEventTest
{
    /** @var InputInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $inputMock;
    /** @var OutputInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $outputMock;
    /** @var Command|\PHPUnit_Framework_MockObject_MockObject */
    protected $commandMock;
    /** @var ProcessBuilder */
    protected $processBuilder;

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

        $this->processBuilder = new ProcessBuilder();
    }

    public function testShouldGetPropertiesPassedInConstructor()
    {
        $event = new RunTestsProcessEvent(
            $this->commandMock,
            $this->inputMock,
            $this->outputMock,
            $this->processBuilder,
            ['foo', 'bar']
        );

        $this->assertSame($this->commandMock, $event->getCommand());
        $this->assertSame($this->inputMock, $event->getInput());
        $this->assertSame($this->outputMock, $event->getOutput());
        $this->assertSame($this->processBuilder, $event->getProcessBuilder());
        $this->assertSame(['foo', 'bar'], $event->getArgs());
    }

    public function testShouldAllowToOverwriteArgsArray()
    {
        $event = new RunTestsProcessEvent(
            $this->commandMock,
            $this->inputMock,
            $this->outputMock,
            $this->processBuilder,
            ['foo', 'bar']
        );

        // Set custom args, overwrite those from constructor
        $event->setArgs(['baz', 'ban']);

        // Check they are retrieved using the getter
        $this->assertSame(['baz', 'ban'], $event->getArgs());
    }
}
