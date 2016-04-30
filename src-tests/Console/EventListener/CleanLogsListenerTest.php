<?php

namespace Lmc\Steward\Console\EventListener;

use Lmc\Steward\Console\Command\CleanCommand;
use Lmc\Steward\Console\Command\Command;
use Lmc\Steward\Console\Command\RunCommand;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers Lmc\Steward\Console\EventListener\CleanLogsListener
 */
class CleanLogsListenerTest extends \PHPUnit_Framework_TestCase
{
    /** @var CleanLogsListener */
    protected $listener;

    protected function setUp()
    {
        $this->listener = new CleanLogsListener();
    }

    public function testShouldSubscribeToEvents()
    {
        $subscribedEvents = $this->listener->getSubscribedEvents();

        $this->assertArrayHasKey(CommandEvents::CONFIGURE, $subscribedEvents);
        $this->assertArrayHasKey(CommandEvents::RUN_TESTS_INIT, $subscribedEvents);
    }

    public function testShouldAddNoCleanOptionToRunTestsCommand()
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

    public function testShouldNotAddNoCleanOptionToDifferentCommand()
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

    public function testShouldInvokeCleanCommand()
    {
        $command = new RunCommand(new EventDispatcher());
        $eventMock = $this->prepareExtendedConsoleEventMock(
            $command,
            new StringInput(''),
            new BufferedOutput()
        );

        $cleanCommandMock = $this->getMockBuilder(CleanCommand::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cleanCommandMock->expects($this->once())
            ->method('run');

        $applicationMock = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->getMock();
        $applicationMock->expects($this->once())
            ->method('find')
            ->with('clean')
            ->willReturn($cleanCommandMock);

        $runCommandMock = $this->getMockBuilder(RunCommand::class)
            ->disableOriginalConstructor()
            ->getMock();
        $runCommandMock->expects($this->once())
            ->method('getApplication')
            ->willReturn($applicationMock);

        $eventMock->expects($this->once())
            ->method('getCommand')
            ->willReturn($runCommandMock);

        $this->listener->onCommandRunTestsInit($eventMock);
    }

    public function testShouldNotInvokeCleanIfNoCleanOptionGiven()
    {
        $command = new RunCommand(new EventDispatcher());
        $eventMock = $this->prepareExtendedConsoleEventMock(
            $command,
            new StringInput('--no-clean'),
            new BufferedOutput()
        );

        $eventMock->expects($this->never())
            ->method('getCommand');

        $this->listener->onCommandRunTestsInit($eventMock);
    }

    /**
     * Prepare ExtendedConsoleEvent that could be passed to onCommandRunTestsInit().
     *
     * @param Command $command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return \PHPUnit_Framework_MockObject_MockObject|ExtendedConsoleEvent
     */
    protected function prepareExtendedConsoleEventMock($command, $input, $output)
    {
        // Trigger event to add the option to the command and bind the definition to the input
        $this->listener->onCommandConfigure(new BasicConsoleEvent($command));
        $input->bind($command->getDefinition());

        $event = $this->getMockBuilder(ExtendedConsoleEvent::class)
            ->setConstructorArgs([$command, $input, $output])
            ->setMethods(['getCommand'])
            ->getMock();

        return $event;
    }
}
