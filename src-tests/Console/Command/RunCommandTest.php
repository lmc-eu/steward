<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Process\ProcessSet;
use Lmc\Steward\Process\ProcessSetCreator;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;

/**
 * @covers Lmc\Steward\Console\Command\RunCommand
 */
class RunCommandTest extends \PHPUnit_Framework_TestCase
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

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Not enough arguments (missing: "environment, browser").
     */
    public function testShouldFailWithoutArguments()
    {
        $this->tester->execute(
            ['command' => $this->command->getName()]
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Not enough arguments (missing: "browser").
     */
    public function testShouldFailWithoutBrowserSpecified()
    {
        $this->tester->execute(
            ['command' => $this->command->getName(), 'environment' => 'staging']
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Not enough arguments (missing: "environment").
     */
    public function testShouldFailWithoutEnvironmentSpecified()
    {
        $this->tester->execute(
            ['command' => $this->command->getName(), 'browser' => 'firefox']
        );
    }

    /**
     * @dataProvider directoryOptionsProvider
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
        $this->setExpectedException('\RuntimeException', $expectedError);

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--' . $directoryOption => '/not/accessible'
            ]
        );
    }

    /**
     * @return array
     */
    public function directoryOptionsProvider()
    {
        return [
            ['tests-dir', 'Path to directory with tests does not exist'],
            ['logs-dir', 'Path to directory with logs does not exist'],
            ['fixtures-dir', 'Base path to directory with fixture files does not exist'],
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
                '--pattern' => 'NotExisting.foo' // so the test stops execution
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
     * @dataProvider browserNameProvider
     * @param string $browserName
     * @param bool $shouldThrowException
     */
    public function testShouldThrowExceptionIfUnsupportedBrowserSelected($browserName, $shouldThrowException)
    {
        if ($shouldThrowException) {
            $this->setExpectedException('\RuntimeException', 'Browser "' . $browserName . '" is not supported');
        }

        $seleniumAdapterMock = $this->getSeleniumAdapterMock();
        $this->command->setSeleniumAdapter($seleniumAdapterMock);

        $this->tester->execute(
            ['command' => $this->command->getName(), 'environment' => 'prod', 'browser' => $browserName]
        );

        if (!$shouldThrowException) {
            $output = $this->tester->getDisplay();
            $this->assertContains('Browser: ' . strtolower($browserName), $output);
            $this->assertContains('No testcases found, exiting.', $output);
        }
    }

    /**
     * @return array
     */
    public function browserNameProvider()
    {
        return [
            // $browserName, $shouldThrowException
            'firefox is supported' => ['firefox', false],
            'chrome is supported' => ['chrome', false],
            'phantomjs is supported' => ['phantomjs', false],
            'browser name is case insensitive' => ['FIREFOX', false],
            'not supported browser' => ['mosaic', true],
            'unprintable character in browser name' => ['firefox​', true],
        ];
    }

    public function testShouldStopIfServerIsNotResponding()
    {
        $seleniumAdapterMock = $this->getMockBuilder(SeleniumServerAdapter::class)
            ->getMock();

        $seleniumAdapterMock->expects($this->once())
            ->method('isAccessible')
            ->with('http://foo.bar:1337')
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
            ]
        );

        $this->assertContains('trying connection...connection error (Foo Bar Error)', $this->tester->getDisplay());
        $this->assertContains(
            'Make sure your Selenium server is really accessible on url "http://foo.bar:1337"',
            $this->tester->getDisplay()
        );
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testShouldStopIfServerIsRespondingButIsNotSelenium()
    {
        $seleniumAdapterMock = $this->getMockBuilder(SeleniumServerAdapter::class)
            ->getMock();

        $seleniumAdapterMock->expects($this->once())
            ->method('isAccessible')
            ->willReturn(true);

        $seleniumAdapterMock->expects($this->once())
            ->method('isSeleniumServer')
            ->with('http://foo.bar:1337')
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
            ]
        );

        $this->assertContains('trying connection...response error (This is teapot)', $this->tester->getDisplay());
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
                '--pattern' => 'NotExisting.foo'
            ]
        );

        $this->assertContains('by pattern "NotExisting.foo"', $this->tester->getDisplay());
        $this->assertContains('No testcases found, exiting.', $this->tester->getDisplay());
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testShouldDispatchConfigureEvent()
    {
        $dispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();

        $dispatcherMock->expects($this->at(0))
            ->method('dispatch')
            ->with($this->equalTo(CommandEvents::CONFIGURE), $this->isInstanceOf(BasicConsoleEvent::class));

        $application = new Application();
        $application->add(new RunCommand($dispatcherMock));
        $command = $application->find('run');
        $command->setSeleniumAdapter($this->getSeleniumAdapterMock());

        (new CommandTester($command))->execute(
            ['command' => $command->getName(), 'environment' => 'staging', 'browser' => 'firefox']
        );
    }

    public function testShouldDispatchInitEvent()
    {
        $dispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();

        $dispatcherMock->expects($this->at(1))
            ->method('dispatch')
            ->with($this->equalTo(CommandEvents::RUN_TESTS_INIT), $this->isInstanceOf(ExtendedConsoleEvent::class));

        $application = new Application();
        $application->add(new RunCommand($dispatcherMock));
        $command = $application->find('run');
        $command->setSeleniumAdapter($this->getSeleniumAdapterMock());

        (new CommandTester($command))->execute(
            ['command' => $command->getName(), 'environment' => 'staging', 'browser' => 'firefox']
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
                '--tests-dir' => __DIR__ . '/Fixtures/DummyTests', // There should by only one test class
            ]
        );

        $this->assertContains('No testcases matched given groups, exiting.', $this->tester->getDisplay());
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    /**
     * @todo Separate to tests for ExecutionLoop
     */
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

        $this->assertContains('No tasks left, exiting the execution loop...', $this->tester->getDisplay());
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
