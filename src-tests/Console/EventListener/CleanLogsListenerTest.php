<?php declare(strict_types=1);

namespace Lmc\Steward\Console\EventListener;

use Lmc\Steward\Console\Application;
use Lmc\Steward\Console\Command\CleanCommand;
use Lmc\Steward\Console\Command\InstallCommand;
use Lmc\Steward\Console\Command\RunCommand;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers \Lmc\Steward\Console\EventListener\CleanLogsListener
 */
class CleanLogsListenerTest extends TestCase
{
    /** @var CleanLogsListener */
    protected $listener;

    protected function setUp(): void
    {
        $this->listener = new CleanLogsListener();
    }

    public function testShouldSubscribeToEvents(): void
    {
        $subscribedEvents = CleanLogsListener::getSubscribedEvents();

        $this->assertArrayHasKey(CommandEvents::CONFIGURE, $subscribedEvents);
        $this->assertArrayHasKey(CommandEvents::PRE_INITIALIZE, $subscribedEvents);
    }

    public function testShouldAddNoCleanOptionToRunTestsCommand(): void
    {
        $command = new RunCommand(new EventDispatcher());

        // Save original options
        $optionsOriginal = $command->getDefinition()->getOptions();

        // Trigger the event
        $this->listener->onCommandConfigure(new BasicConsoleEvent($command));

        // The original options should now be appended with no-clean option
        $optionsWithNoClean = $command->getDefinition()->getOptions();

        $this->assertCount(count($optionsOriginal) + 1, $optionsWithNoClean);
        $this->assertArrayHasKey(CleanLogsListener::OPTION_NO_CLEAN, $optionsWithNoClean);
    }

    public function testShouldNotAddNoCleanOptionToDifferentCommand(): void
    {
        $renamedCommand = new RunCommand(new EventDispatcher());
        $renamedCommand->setName('foo-bar');

        // Save original options
        $optionsOriginal = $renamedCommand->getDefinition()->getOptions();

        // Trigger the event
        $this->listener->onCommandConfigure(new BasicConsoleEvent($renamedCommand));

        // The new options should be still the same, not altered
        $optionsNew = $renamedCommand->getDefinition()->getOptions();
        $this->assertSame($optionsOriginal, $optionsNew);
    }

    public function testShouldInvokeCleanCommand(): void
    {
        $cleanCommandMock = $this->createMock(CleanCommand::class);
        $cleanCommandMock->expects($this->once())
            ->method('run');

        $applicationMock = $this->createMock(Application::class);
        $applicationMock->expects($this->once())
            ->method('getHelperSet')
            ->willReturn(new HelperSet());
        $applicationMock->expects($this->once())
            ->method('find')
            ->with('clean')
            ->willReturn($cleanCommandMock);

        $command = $this->initializeRunCommandWithApplication($applicationMock);

        $input = new StringInput('');
        $input->bind($command->getDefinition());

        $event = new ExtendedConsoleEvent($command, $input, new BufferedOutput());

        $this->listener->onCommandPreInitialize($event);
    }

    public function testShouldNotInvokeCleanCommandFromOtherCommandThanRun(): void
    {
        $applicationMock = $this->createMock(Application::class);
        $applicationMock->expects($this->once())
            ->method('getHelperSet')
            ->willReturn(new HelperSet());
        $applicationMock->expects($this->never())
            ->method('find')
            ->with('clean');

        $command = new InstallCommand(new EventDispatcher());
        $command->setApplication($applicationMock);

        $this->listener->onCommandConfigure(new BasicConsoleEvent($command));

        $input = new StringInput('');
        $input->bind($command->getDefinition());

        $event = new ExtendedConsoleEvent($command, $input, new BufferedOutput());

        $this->listener->onCommandPreInitialize($event);
    }

    public function testShouldNotInvokeCleanIfNoCleanOptionGiven(): void
    {
        $applicationMock = $this->createMock(Application::class);
        $applicationMock->expects($this->once())
            ->method('getHelperSet')
            ->willReturn(new HelperSet());
        $applicationMock->expects($this->never())
            ->method('find')
            ->with('clean');

        $command = $this->initializeRunCommandWithApplication($applicationMock);

        $input = new StringInput('--no-clean');
        $input->bind($command->getDefinition());

        $event = new ExtendedConsoleEvent($command, $input, new BufferedOutput());

        $this->listener->onCommandPreInitialize($event);
    }

    private function initializeRunCommandWithApplication(Application $application): RunCommand
    {
        $command = new RunCommand(new EventDispatcher());
        $command->setApplication($application);
        $this->listener->onCommandConfigure(new BasicConsoleEvent($command));

        return $command;
    }
}
