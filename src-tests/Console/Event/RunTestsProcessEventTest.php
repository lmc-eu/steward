<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Event;

use Lmc\Steward\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunTestsProcessEventTest extends ExtendedConsoleEventTest
{
    /** @var InputInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $inputMock;
    /** @var OutputInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $outputMock;
    /** @var Command|\PHPUnit_Framework_MockObject_MockObject */
    protected $commandMock;

    protected function setUp(): void
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

    public function testShouldGetPropertiesPassedInConstructor(): void
    {
        $event = new RunTestsProcessEvent(
            $this->commandMock,
            $this->inputMock,
            $this->outputMock,
            ['env1' => 'foo', 'env2' => 'bar'],
            ['foo', 'bar']
        );

        $this->assertSame($this->commandMock, $event->getCommand());
        $this->assertSame($this->inputMock, $event->getInput());
        $this->assertSame($this->outputMock, $event->getOutput());
        $this->assertSame(['env1' => 'foo', 'env2' => 'bar'], $event->getEnvironmentVars());
        $this->assertSame(['foo', 'bar'], $event->getArgs());
    }

    public function testShouldAllowToOverwriteEnvironmentVariablesAndArgsArray(): void
    {
        $event = new RunTestsProcessEvent(
            $this->commandMock,
            $this->inputMock,
            $this->outputMock,
            ['env1' => 'foo', 'env2' => 'bar'],
            ['foo', 'bar']
        );

        // Set custom env, overwrite those from constructor
        $event->setEnvironmentVars(['env3' => 'bak', 'env4' => 'bat']);

        // Check they are retrieved using the getter
        $this->assertSame(['env3' => 'bak', 'env4' => 'bat'], $event->getEnvironmentVars());

        // Set custom args, overwrite those from constructor
        $event->setArgs(['baz', 'ban']);

        // Check they are retrieved using the getter
        $this->assertSame(['baz', 'ban'], $event->getArgs());
    }
}
