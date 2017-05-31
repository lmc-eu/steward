<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\Application;
use Lmc\Steward\LineEndingsNormalizerTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers Lmc\Steward\Console\Command\ResultsCommand
 */
class ResultsCommandTest extends TestCase
{
    use LineEndingsNormalizerTrait;

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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot read results file "/not/accessible.xml"');

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--results-file' => '/not/accessible.xml',
            ]
        );
    }

    public function testShouldOutputTestcasesResult()
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--results-file' => __DIR__ . '/Fixtures/dummy-logs/results-basic.xml',
            ]
        );

        $output = $this->normalizeLineEndings($this->tester->getDisplay());

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringEqualsFile(__DIR__ . '/Fixtures/dummy-logs/expected-output-basic.txt', $output);
    }

    public function testShouldOutputTestcasesAndTestsResultInDebugMode()
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--results-file' => __DIR__ . '/Fixtures/dummy-logs/results-basic.xml',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $output = $this->normalizeLineEndings($this->tester->getDisplay());

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringEqualsFile(__DIR__ . '/Fixtures/dummy-logs/expected-output-with-tests.txt', $output);
    }

    public function testShouldOutputRunningTimeOfStartedTest()
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--results-file' => __DIR__ . '/Fixtures/dummy-logs/results-running.xml',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertContains('|  - testBanStarted   | started  |        | 2016-04-29 12:33:33 |          |', $output);
        $this->assertRegExp('/| \d+ sec/', $output);

        $this->assertContains(
            'Testcases (1 total): prepared: 1, running: 0, done: 0 (passed: 0, failed: 0, fatal: 0',
            $output
        );
        $this->assertContains(
            'Tests (1 so far): started: 1, done: 0 (passed: 0, failed or broken: 0, skipped or incomplete: 0)',
            $output
        );
    }

    public function testShouldNotShowEndingTimeOfFataledTest()
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--results-file' => __DIR__ . '/Fixtures/dummy-logs/results-fatal.xml',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $output = $this->normalizeLineEndings($this->tester->getDisplay());

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringEqualsFile(__DIR__ . '/Fixtures/dummy-logs/expected-output-fatal.txt', $output);
    }
}
