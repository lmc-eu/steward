<?php

namespace Lmc\Steward\Console\Command;

use Assert\InvalidArgumentException;
use Lmc\Steward\Console\Application;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Process\ProcessSet;
use Lmc\Steward\Process\ProcessSetCreator;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;

/**
 * @covers Lmc\Steward\Console\Command\RunCommand
 */
class RunCommandTest extends TestCase
{
    /** @var RunCommand */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    protected function setUp()
    {
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->add(new RunCommand($dispatcher));

        $this->command = $application->find('run');
        $this->tester = new CommandTester($this->command);
    }

    public function testShouldFailWithoutArguments()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "environment, browser").');

        $this->tester->execute(
            ['command' => $this->command->getName()]
        );
    }

    public function testShouldFailWithoutBrowserSpecified()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "browser").');

        $this->tester->execute(
            ['command' => $this->command->getName(), 'environment' => 'staging']
        );
    }

    public function testShouldFailWithoutEnvironmentSpecified()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "environment").');

        $this->tester->execute(
            ['command' => $this->command->getName(), 'browser' => 'firefox']
        );
    }

    /**
     * @dataProvider provideDirectoryOptions
     * @param string $directoryOption Passed path type option
     * @param string $errorBeginning Beginning of exception message
     */
    public function testShouldThrowExceptionIfAnyRequiredDirectoryIsNotAccessible($directoryOption, $errorBeginning)
    {
        $seleniumAdapterMock = $this->getSeleniumAdapterMock();
        $this->command->setSeleniumAdapter($seleniumAdapterMock);

        $expectedError = sprintf(
            '%s, make sure it is accessible or define your own path using --%s option',
            $errorBeginning,
            $directoryOption
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedError);

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--' . $directoryOption => '/not/accessible',
            ]
        );
    }

    /**
     * @return array
     */
    public function provideDirectoryOptions()
    {
        return [
            ['tests-dir', 'Path to directory with tests "/not/accessible" does not exist'],
            ['logs-dir', 'Path to directory with logs "/not/accessible" does not exist'],
            ['fixtures-dir', 'Base path to directory with fixture files "/not/accessible" does not exist'],
        ];
    }

    public function testShouldOutputAssembledPathsToDirectoriesInDebugMode()
    {
        $seleniumAdapterMock = $this->getSeleniumAdapterMock();
        $this->command->setSeleniumAdapter($seleniumAdapterMock);

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--tests-dir' => __DIR__ . '/Fixtures/DummyTests',
                '--logs-dir' => __DIR__ . '/Fixtures/logs',
                '--fixtures-dir' => __DIR__ . '/Fixtures/tests',
                '--pattern' => 'NotExisting.foo', // so the test stops execution
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $output = $this->tester->getDisplay();
        $this->assertContains('Base path to fixtures results: ' . realpath(__DIR__) . '/Fixtures/tests', $output);
        $this->assertContains('Path to logs: ' . realpath(__DIR__) . '/Fixtures/logs', $output);
        $this->assertContains(' - in directory "' . realpath(__DIR__) . '/Fixtures/DummyTests"', $output);
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    /**
     * @dataProvider provideBrowserName
     * @param string $browserName
     * @param string $expectedNameInOutput
     * @param bool $shouldThrowException
     */
    public function testShouldThrowExceptionOnlyIfUnsupportedBrowserSelected(
        $browserName,
        $expectedNameInOutput,
        $shouldThrowException
    ) {
        $seleniumAdapterMock = $this->getSeleniumAdapterMock();
        $this->command->setSeleniumAdapter($seleniumAdapterMock);

        if ($shouldThrowException) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Browser "' . $browserName . '" is not supported');
        }

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'prod',
                'browser' => $browserName,
                '--tests-dir' => __DIR__ . '/Fixtures/tests',
                '--fixtures-dir' => __DIR__,
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        if (!$shouldThrowException) {
            $output = $this->tester->getDisplay();
            $this->assertContains('Browser: ' . $expectedNameInOutput, $output);
            $this->assertContains('No testcases found, exiting.', $output);
        }
    }

    /**
     * @return array
     */
    public function provideBrowserName()
    {
        return [
            // $browserName, $expectedNameInOutput, $shouldThrowException
            'firefox is supported' => ['firefox', 'firefox', false],
            'chrome is supported' => ['chrome', 'chrome', false],
            'phantomjs is supported' => ['phantomjs', 'phantomjs', false],
            'MicrosoftEdge is supported' => ['MicrosoftEdge', 'MicrosoftEdge', false],
            'MicrosoftEdge is supported in lowercase' => ['microsoftedge', 'MicrosoftEdge', false],
            'browser name is case insensitive' => ['FIREFOX', 'firefox', false],
            'not supported browser' => ['mosaic', null, true],
            'unprintable character in browser name' => ['firefox​', null, true],
        ];
    }

    public function testShouldStopIfServerIsNotResponding()
    {
        $seleniumAdapterMock = $this->getMockBuilder(SeleniumServerAdapter::class)
            ->setConstructorArgs(['http://foo.bar:1337'])
            ->setMethods(['isAccessible', 'getLastError'])
            ->getMock();

        $seleniumAdapterMock->expects($this->once())
            ->method('isAccessible')
            ->willReturn(false);

        $seleniumAdapterMock->expects($this->once())
            ->method('getLastError')
            ->willReturn('Foo Bar Error');

        $this->command->setSeleniumAdapter($seleniumAdapterMock);
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--server-url' => 'http://foo.bar:1337',
                '--tests-dir' => __DIR__ . '/Fixtures/tests',
            ]
        );

        $this->assertContains('Error connecting to Selenium server ("Foo Bar Error")', $this->tester->getDisplay());
        $this->assertContains(
            'Make sure your Selenium server is really accessible on url "http://foo.bar:1337"',
            $this->tester->getDisplay()
        );
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testShouldStopIfServerIsRespondingButIsNotSelenium()
    {
        $seleniumAdapterMock = $this->getMockBuilder(SeleniumServerAdapter::class)
            ->setConstructorArgs(['http://foo.bar:1337'])
            ->setMethods(['isAccessible', 'isSeleniumServer', 'getLastError'])
            ->getMock();

        $seleniumAdapterMock->expects($this->once())
            ->method('isAccessible')
            ->willReturn(true);

        $seleniumAdapterMock->expects($this->once())
            ->method('isSeleniumServer')
            ->willReturn(false);

        $seleniumAdapterMock->expects($this->once())
            ->method('getLastError')
            ->willReturn('This is teapot');

        $this->command->setSeleniumAdapter($seleniumAdapterMock);
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--server-url' => 'http://foo.bar:1337',
                '--tests-dir' => __DIR__ . '/Fixtures/tests',
            ]
        );

        $this->assertContains('Unexpected response from Selenium server (This is teapot)', $this->tester->getDisplay());
        $this->assertContains(
            'Looks like url "http://foo.bar:1337" is occupied by something else than Selenium server.',
            $this->tester->getDisplay()
        );
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testShouldStopIfNoTestcasesFoundByGivenFilePattern()
    {
        $seleniumAdapterMock = $this->getSeleniumAdapterMock();
        $this->command->setSeleniumAdapter($seleniumAdapterMock);

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--pattern' => 'NotExisting.foo',
                '--tests-dir' => __DIR__ . '/Fixtures/tests',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $this->assertContains('by pattern "NotExisting.foo"', $this->tester->getDisplay());
        $this->assertContains('No testcases found, exiting.', $this->tester->getDisplay());
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testShouldDispatchEventsOnExecute()
    {
        $dispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();

        $dispatcherMock->expects($this->at(0))
            ->method('dispatch')
            ->with($this->equalTo(CommandEvents::CONFIGURE), $this->isInstanceOf(BasicConsoleEvent::class));

        $dispatcherMock->expects($this->at(1))
            ->method('dispatch')
            ->with($this->equalTo(CommandEvents::PRE_INITIALIZE), $this->isInstanceOf(ExtendedConsoleEvent::class));

        $dispatcherMock->expects($this->at(2))
            ->method('dispatch')
            ->with($this->equalTo(CommandEvents::RUN_TESTS_INIT), $this->isInstanceOf(ExtendedConsoleEvent::class));

        $application = new Application();
        $application->add(new RunCommand($dispatcherMock));
        /** @var RunCommand $command */
        $command = $application->find('run');
        $command->setSeleniumAdapter($this->getSeleniumAdapterMock());

        (new CommandTester($command))->execute(
            [
                'command' => $command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--tests-dir' => __DIR__ . '/Fixtures/tests',
            ]
        );
    }

    public function testShouldStopIfNoTestcasesWereFoundInTheFiles()
    {
        $seleniumAdapterMock = $this->getSeleniumAdapterMock();
        $creatorMock = $this->getMockBuilder(ProcessSetCreator::class)
            ->disableOriginalConstructor()
            ->setMethods(['createFromFiles'])
            ->getMock();

        // Mock createFromFiles() to return empty processSet, and also ensure groups are passed to the processSetCreator
        $creatorMock->expects($this->once())
            ->method('createFromFiles')
            ->with(
                $this->logicalAnd($this->isInstanceOf(Finder::class), $this->countOf(1)),
                ['included'],
                ['excluded']
            )
            ->willReturn(new ProcessSet());

        $this->command->setSeleniumAdapter($seleniumAdapterMock);
        $this->command->setProcessSetCreator($creatorMock);

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--group' => ['included'],
                '--exclude-group' => ['excluded'],
                '--tests-dir' => __DIR__ . '/Fixtures/DummyTests', // There should by only one test class in the dir
            ]
        );

        $this->assertContains('No testcases matched given groups, exiting.', $this->tester->getDisplay());
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testShouldExitSuccessfullyIfNoProcessArePreparedOrQueued()
    {
        $seleniumAdapterMock = $this->getSeleniumAdapterMock();

        $processSetMock = $this->getMockBuilder(ProcessSet::class)
            ->disableOriginalConstructor()
            ->getMock();
        $processSetMock->expects($this->any())
            ->method('count')
            ->willReturn(333);

        $creatorMock = $this->getMockBuilder(ProcessSetCreator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $creatorMock->expects($this->once())
            ->method('createFromFiles')
            ->willReturn($processSetMock);

        $this->command->setSeleniumAdapter($seleniumAdapterMock);
        $this->command->setProcessSetCreator($creatorMock);

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--tests-dir' => __DIR__ . '/Fixtures/DummyTests',
            ]
        );

        $this->assertContains('Testcases executed: 0', $this->tester->getDisplay());
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * Mock Selenium adapter as if connection is OK
     *
     * @return SeleniumServerAdapter|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getSeleniumAdapterMock()
    {
        $seleniumAdapterMock = $this->getMockBuilder(SeleniumServerAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $seleniumAdapterMock->expects($this->any())
            ->method('isAccessible')
            ->willReturn(true);

        $seleniumAdapterMock->expects($this->any())
            ->method('getLastError')
            ->willReturn(null);

        $seleniumAdapterMock->expects($this->any())
            ->method('isSeleniumServer')
            ->willReturn(true);

        return $seleniumAdapterMock;
    }
}
