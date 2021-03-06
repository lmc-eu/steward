<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command;

use Assert\InvalidArgumentException;
use Lmc\Steward\Console\Application;
use Lmc\Steward\Console\Command\Fixtures\DummyCommand;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers \Lmc\Steward\Console\Command\Command
 */
class CommandTest extends TestCase
{
    /** @var Command */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    protected function setUp(): void
    {
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->add(new DummyCommand($dispatcher, 'command'));

        /** @var Command $command */
        $command = $application->find('command');
        $this->command = $command;
        $this->tester = new CommandTester($this->command);
    }

    public function testShouldDispatchPreInitializeEvent(): void
    {
        $dispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();

        $dispatcherMock->expects($this->at(0))
            ->method('dispatch')
            ->with($this->isInstanceOf(ExtendedConsoleEvent::class), $this->equalTo(CommandEvents::PRE_INITIALIZE));

        $application = new Application();
        $application->add(new DummyCommand($dispatcherMock, 'command'));

        $command = $application->find('command');
        $tester = new CommandTester($command);

        $tester->execute(['command' => $command]);
    }

    public function testShouldThrowExceptionIfIConfigFileDoesNotExists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File "/not/existing" was expected to exist.');

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--configuration' => '/not/existing',
            ]
        );
    }
}
