<?php

namespace Lmc\Steward\Console\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

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

    public function testShouldStopIfServerIsNotResponding()
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--server-url' => 'http://localhost:50000',
            ]
        );

        $this->assertContains('Environment: staging', $this->tester->getDisplay());
        $this->assertContains('Browser: firefox', $this->tester->getDisplay());
        $this->assertContains('trying connection...error (Connection refused)', $this->tester->getDisplay());
        $this->assertContains(
            'Make sure your Selenium server is really accessible on url "http://localhost:50000"',
            $this->tester->getDisplay()
        );
    }

    public function testShouldStopIfRespondingServerIsNotSelenium()
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--server-url' => 'http://google.com:80', // to overcome connection check
                'environment' => 'staging',
                'browser' => 'firefox',
            ]
        );

        $this->assertContains(
            'Looks like url "http://google.com:80" is occupied by something else than Selenium server.',
            $this->tester->getDisplay()
        );
    }
}
