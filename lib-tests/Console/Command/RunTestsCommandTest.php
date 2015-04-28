<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Test\SeleniumAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers Lmc\Steward\Console\Command\RunTestsCommand
 */
class RunTestsCommandTest extends \PHPUnit_Framework_TestCase
{
    /** @var RunTestsCommand */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    public static function setUpBeforeClass()
    {
        define('STEWARD_BASE_DIR', __DIR__ . '/Fixtures');
    }

    protected function setUp()
    {
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->add(new RunTestsCommand($dispatcher));

        $this->command = $application->find('run-tests');
        $this->tester = new CommandTester($this->command);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Not enough arguments.
     */
    public function testShouldFailWithoutArguments()
    {
        $this->tester->execute(
            ['command' => $this->command->getName()]
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Not enough arguments.
     */
    public function testShouldFailWithoutBrowserSpecified()
    {
        $this->tester->execute(
            ['command' => $this->command->getName(), 'environment' => 'staging']
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Not enough arguments.
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
     * @param string $errorBeginning Beginning of error message
     */
    public function testShouldStopIfAnyRequiredDirectoryIsNotAccessible($directoryOption, $errorBeginning)
    {
        $seleniumAdapterMock = $this->getSeleniumAdapterMock();
        $this->command->setSeleniumAdapter($seleniumAdapterMock);

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--' . $directoryOption => '/not/accessible'
            ]
        );

        $expectedError = sprintf(
            '%s, make sure it is accessible or define your own path using --%s option',
            $errorBeginning,
            $directoryOption
        );

        $this->assertContains($expectedError, $this->tester->getDisplay());
        $this->assertSame(1, $this->tester->getStatusCode());
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

    public function testShouldStopIfServerIsNotResponding()
    {
        $seleniumAdapterMock = $this->getMockBuilder(SeleniumAdapter::class)
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
        $seleniumAdapterMock = $this->getMockBuilder(SeleniumAdapter::class)
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

    public function testShouldStopIfNoTestcasesFoundByGivenPatternAnd()
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
        $this->assertContains('No testcases matched given criteria, exiting.', $this->tester->getDisplay());
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    /**
     * Get Selenium adapter mocking connection as OK
     * @return SeleniumAdapter|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getSeleniumAdapterMock()
    {
        $seleniumAdapterMock = $this->getMockBuilder(SeleniumAdapter::class)
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
