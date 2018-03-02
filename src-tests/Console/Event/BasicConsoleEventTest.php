<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Event;

use Lmc\Steward\Console\Command\Command;
use PHPUnit\Framework\TestCase;

class BasicConsoleEventTest extends TestCase
{
    /** @var Command|\PHPUnit_Framework_MockObject_MockObject */
    protected $commandMock;

    protected function setUp(): void
    {
        $this->commandMock = $this->createMock(Command::class);
    }

    public function testShouldGetPropertiesPassedInConstructor(): void
    {
        $event = new BasicConsoleEvent($this->commandMock);
        $this->assertSame($this->commandMock, $event->getCommand());
    }
}
