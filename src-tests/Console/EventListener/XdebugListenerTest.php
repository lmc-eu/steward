<?php

namespace Lmc\Steward\Console\EventListener;

use Lmc\Steward\Console\Command\Command;
use Lmc\Steward\Console\Command\RunCommand;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers \Lmc\Steward\Console\EventListener\XdebugListener
 */
class XdebugListenerTest extends TestCase
{
    use PHPMock;

    /** @var XdebugListener */
    protected $listener;

    protected function setUp()
    {
        $this->listener = new XdebugListener();
    }

    public function testShouldSubscribeToEvents()
    {
        $subscribedEvents = XdebugListener::getSubscribedEvents();

        $this->assertArrayHasKey(CommandEvents::CONFIGURE, $subscribedEvents);
        $this->assertArrayHasKey(CommandEvents::RUN_TESTS_INIT, $subscribedEvents);
        $this->assertArrayHasKey(CommandEvents::RUN_TESTS_PROCESS, $subscribedEvents);
    }

    public function testShouldAddXdebugOptionToRunTestsCommand()
    {
        $command = new RunCommand(new EventDispatcher());

        // Save original options
        $optionsOriginal = $command->getDefinition()->getOptions();

        // Trigger the event
        $this->listener->onCommandConfigure(new BasicConsoleEvent($command));

        // The original options should now be appended with xdebug option
        $optionsWithXdebug = $command->getDefinition()->getOptions();

        $this->assertCount(count($optionsOriginal) + 1, $optionsWithXdebug);
        $this->assertArrayHasKey(XdebugListener::OPTION_XDEBUG, $optionsWithXdebug);
    }

    public function testShouldNotAddXdebugOptionToDifferentCommand()
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

    /**
     * @dataProvider provideInput
     * @param string $stringInput
     * @param string|null $expectedIdeKey
     */
    public function testShouldGetIdeKeyFromCommandOptionOnCommandInitialization($stringInput, $expectedIdeKey)
    {
        $this->mockXdebugExtension($isExtensionLoaded = true, $isRemoteEnabled = true);
        $command = new RunCommand(new EventDispatcher());

        $input = new StringInput($stringInput);
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        // Trigger event to add the xdebug option to the command and bind the definition to the input
        $this->listener->onCommandConfigure(new BasicConsoleEvent($command));
        $input->bind($command->getDefinition());

        // Trigger command initialization event
        $event = new ExtendedConsoleEvent($command, $input, $output);
        $this->listener->onCommandRunTestsInit($event);

        if ($expectedIdeKey !== null) {
            $this->assertContains(
                'Xdebug remote debugging initialized with IDE key: ' . $expectedIdeKey,
                $output->fetch()
            );
        } else { // no output expected (xdebug not triggered)
            $this->assertEmpty($output->fetch());
        }
    }

    public function provideInput()
    {
        return [
            'option not passed at all => no idekey' => ['run', null],
            'option passed without custom value => default idekey' => ['run --xdebug', 'phpstorm'],
            'option passed with empty custom value => no idekey' => ['run --xdebug=""', null],
            'option passed without custom value => default idekey (combined with another option)' => [
                'run --xdebug --no-exit',
                'phpstorm',
            ],
            'custom idekey passed' => ['run --xdebug=custom', 'custom'],
            'custom idekey passed (combined with another option)' => ['run --xdebug=custom --no-exit', 'custom'],
            'custom idekey passed (but with default value of idekey)' => [
                'run --xdebug=' . XdebugListener::DEFAULT_VALUE,
                'phpstorm',
            ],
        ];
    }

    public function testShouldFailWhenXdebugExtensionIsNotLoaded()
    {
        $this->mockXdebugExtension($isExtensionLoaded = false, $isRemoteEnabled = false);

        $command = new RunCommand(new EventDispatcher());
        $event = $this->prepareExtendedConsoleEvent(
            $command,
            new StringInput('env firefox --xdebug'),
            new BufferedOutput()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Extension Xdebug is not loaded or installed');

        $this->listener->onCommandRunTestsInit($event);
    }

    public function testShouldFailWhenXdebugExtensionIsLoadedButRemoteDebugIsNotEnabled()
    {
        $this->mockXdebugExtension($isExtensionLoaded = true, $isRemoteEnabled = false);

        $command = new RunCommand(new EventDispatcher());
        $event = $this->prepareExtendedConsoleEvent(
            $command,
            new StringInput('env firefox --xdebug'),
            new BufferedOutput()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The xdebug.remote_enable directive must be set to true to enable remote debugging.'
        );

        $this->listener->onCommandRunTestsInit($event);
    }

    public function testShouldInjectEnvironmentVariableOnProcessRunEventIfXdebugOptionWasPassed()
    {
        $this->mockXdebugExtension($isExtensionLoaded = true, $isRemoteEnabled = true);

        $command = new RunCommand(new EventDispatcher());
        $input = new StringInput('env firefox --xdebug');
        $output = new BufferedOutput();

        // Trigger onCommandRunTestsInit() so the idekey value gets stored in the listener
        $extendedConsoleEvent = $this->prepareExtendedConsoleEvent($command, $input, $output);
        $this->listener->onCommandRunTestsInit($extendedConsoleEvent);

        $originalEnv = ['FOO' => 'bar'];
        $event = new RunTestsProcessEvent($command, $input, $output, $originalEnv, []);
        $this->listener->onCommandRunTestsProcess($event);

        $this->assertSame(['FOO' => 'bar', 'XDEBUG_CONFIG' => 'idekey=phpstorm'], $event->getEnvironmentVars());
    }

    public function testShouldNotInjectEnvironmentVariableIfXdebugOptionWasNotPassed()
    {
        $this->mockXdebugExtension($isExtensionLoaded = true, $isRemoteEnabled = true);

        $command = new RunCommand(new EventDispatcher());
        $input = new StringInput('env firefox');
        $output = new BufferedOutput();

        // Trigger onCommandRunTestsInit(), but is should not do anything, as we didn't passed the --xdebug option
        $extendedConsoleEvent = $this->prepareExtendedConsoleEvent($command, $input, $output);
        $this->listener->onCommandRunTestsInit($extendedConsoleEvent);

        $originalEnv = ['FOO' => 'bar'];
        $event = new RunTestsProcessEvent($command, $input, $output, $originalEnv, []);
        $this->listener->onCommandRunTestsProcess($event);

        $this->assertSame($originalEnv, $event->getEnvironmentVars());
    }

    /**
     * Prepare ExtendedConsoleEvent that could be passed to onCommandRunTestsInit().
     *
     * @param Command $command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return ExtendedConsoleEvent
     */
    protected function prepareExtendedConsoleEvent($command, $input, $output)
    {
        // Trigger event to add the xdebug option to the command and bind the definition to the input
        $this->listener->onCommandConfigure(new BasicConsoleEvent($command));
        $input->bind($command->getDefinition());

        $event = new ExtendedConsoleEvent($command, $input, $output);

        return $event;
    }

    /**
     * Mock xdebug extension status.
     *
     * @param bool $isExtensionLoaded Mocked extension_loaded('xdebug') value
     * @param bool $isRemoteEnabled Mocked ini_get('xdebug.remote_enable') value
     */
    protected function mockXdebugExtension($isExtensionLoaded, $isRemoteEnabled)
    {
        $extensionLoadedMock = $this->getFunctionMock(__NAMESPACE__, 'extension_loaded');
        $extensionLoadedMock->expects($this->any())
            ->with('xdebug')
            ->willReturn($isExtensionLoaded);

        $iniGetMock = $this->getFunctionMock(__NAMESPACE__, 'ini_get');
        $iniGetMock->expects($this->any())
            ->with('xdebug.remote_enable')
            ->willReturn($isRemoteEnabled);
    }
}
