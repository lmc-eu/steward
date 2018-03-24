<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Event;

use Lmc\Steward\Console\Command\Command;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtendedConsoleEventTest extends BasicConsoleEventTest
{
    /** @var InputInterface|MockObject */
    protected $inputMock;
    /** @var OutputInterface|MockObject */
    protected $outputMock;
    /** @var Command|MockObject */
    protected $commandMock;

    protected function setUp(): void
    {
        $this->commandMock = $this->createMock(Command::class);

        $this->inputMock = $this->createMock(InputInterface::class);

        $this->outputMock = $this->createMock(OutputInterface::class);
    }

    public function testShouldGetPropertiesPassedInConstructor(): void
    {
        $event = new ExtendedConsoleEvent($this->commandMock, $this->inputMock, $this->outputMock);
        $this->assertSame($this->commandMock, $event->getCommand());
        $this->assertSame($this->inputMock, $event->getInput());
        $this->assertSame($this->outputMock, $event->getOutput());
    }
}
