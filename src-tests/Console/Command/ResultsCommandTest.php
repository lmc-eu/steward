<?php

namespace Lmc\Steward\Console\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers Lmc\Steward\Console\Command\ResultsCommand
 */
class ResultsCommandTest extends \PHPUnit_Framework_TestCase
{
    /** @var ResultsCommand */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    protected function setUp()
    {
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->add(new ResultsCommand($dispatcher));

        $this->command = $application->find('results');
        $this->tester = new CommandTester($this->command);
    }

    public function testShouldShowErrorIfResultsFileCannotBeFound()
    {
        $this->setExpectedException(\RuntimeException::class, 'Cannot read results file "/not/accessible/results.xml"');

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--logs-dir' => '/not/accessible'
            ]
        );
    }

    public function testShouldOutputTestcasesResult()
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--logs-dir' => __DIR__ . '/Fixtures/dummy-logs-basic',
            ]
        );

        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringEqualsFile(__DIR__ . '/Fixtures/dummy-logs-basic/expected-output.txt', $output);
    }

    public function testShouldOutputTestcasesAndTestsResultInDebugMode()
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--logs-dir' => __DIR__ . '/Fixtures/dummy-logs-basic',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringEqualsFile(__DIR__ . '/Fixtures/dummy-logs-basic/expected-output-with-tests.txt', $output);
    }

    public function testShouldOutputRunningTimeOfStartedTest()
    {
        $testStartTimestamp = 1461926013; // 2016-04-29 12:33:33+0200
        $expectedDuration = time() - $testStartTimestamp;
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--logs-dir' => __DIR__ . '/Fixtures/dummy-logs-running',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertContains('|  - testBanStarted   | started  |        | 2016-04-29 12:33:33 |          |', $output);
        $this->assertRegExp('/| \d+ sec/', $output);

    }

    public function testShouldNotShowEndingTimeOfFataledTest()
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--logs-dir' => __DIR__ . '/Fixtures/dummy-logs-fatal',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringEqualsFile(__DIR__ . '/Fixtures/dummy-logs-fatal/expected-output.txt', $output);
    }
}
