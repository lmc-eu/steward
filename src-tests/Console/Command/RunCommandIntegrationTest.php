<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Run command tests that require real Selenium server to execute the tests.
 *
 * @covers \Lmc\Steward\Console\Command\RunCommand
 * @covers \Lmc\Steward\Listener\WebDriverListener
 * @covers \Lmc\Steward\Process\ExecutionLoop
 * @group integration
 * @runTestsInSeparateProcesses
 */
class RunCommandIntegrationTest extends TestCase
{
    /** @var RunCommand */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    protected function setUp(): void
    {
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->add(new RunCommand($dispatcher));

        /** @var RunCommand $command */
        $command = $application->find('run');
        $this->command = $command;
        $this->tester = new CommandTester($this->command);
    }

    /**
     * @dataProvider provideExpectedTestOutput
     */
    public function testShouldExecuteSimpleTests(int $outputVerbosity, string $expectedOutputFile): void
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'chrome',
                '--tests-dir' => __DIR__ . '/Fixtures/SimpleTests',
            ],
            ['verbosity' => $outputVerbosity]
        );

        $output = $this->tester->getDisplay();

        $this->assertStringMatchesFormatFile(__DIR__ . '/Fixtures/SimpleTests/' . $expectedOutputFile, $output);

        $this->assertSame(0, $this->tester->getStatusCode());

        // Make sure the results were written to results file
        /** @var \SimpleXMLElement $xml */
        $xml = simplexml_load_file(STEWARD_BASE_DIR . '/logs/results.xml');

        $simpleTest = $xml->testcase[1];
        $dependantTest = $xml->testcase[0];
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml->testcase);
        $this->assertCount(2, $xml->testcase);
        $this->assertEquals('Lmc\Steward\Console\Command\Fixtures\SimpleTests\DependantTest', $dependantTest['name']);
        $this->assertEquals('Lmc\Steward\Console\Command\Fixtures\SimpleTests\SimpleTest', $simpleTest['name']);
        $this->assertEquals('done', $dependantTest['status']);
        $this->assertEquals('done', $simpleTest['status']);
        $this->assertEquals('passed', $dependantTest['result']);
        $this->assertEquals('passed', $simpleTest['result']);

        $this->assertInstanceOf(\SimpleXMLElement::class, $simpleTest->test);
        $this->assertCount(1, $simpleTest->test);
        $this->assertEquals('testWebpage', $simpleTest->test['name']);
        $this->assertEquals('done', $simpleTest->test['status']);
        $this->assertEquals('passed', $simpleTest->test['result']);
    }

    /**
     * @return array[]
     */
    public function provideExpectedTestOutput(): array
    {
        return [
            [OutputInterface::VERBOSITY_NORMAL, 'expected-normal-output.txt'],
            [OutputInterface::VERBOSITY_VERBOSE, 'expected-verbose-output.txt'],
            [OutputInterface::VERBOSITY_VERY_VERBOSE, 'expected-very-verbose-output.txt'],
            [OutputInterface::VERBOSITY_DEBUG, 'expected-debug-output.txt'],
        ];
    }

    public function testShouldExecuteTestsWithParallelLimit(): void
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'chrome',
                '--tests-dir' => __DIR__ . '/Fixtures/ParallelTests',
                '--parallel-limit' => 1,
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $output = $this->tester->getDisplay();

        $this->assertStringMatchesFormatFile(__DIR__ . '/Fixtures/ParallelTests/expected-debug-output.txt', $output);

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldExecuteTestsThatDontNeedBrowser(): void
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'internet explorer', // intentionally use unavailable browser to make sure it is not used
                '--tests-dir' => __DIR__ . '/Fixtures/NoBrowserTests',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $output = $this->tester->getDisplay();

        $this->assertStringMatchesFormatFile(__DIR__ . '/Fixtures/NoBrowserTests/expected-output.txt', $output);
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @dataProvider provideExpectedFailingTestOutput
     */
    public function testShouldProcessFailedTests(int $outputVerbosity, string $expectedOutputFile): void
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'chrome',
                '--tests-dir' => __DIR__ . '/Fixtures/FailingTests',
            ],
            ['verbosity' => $outputVerbosity]
        );

        $output = $this->tester->getDisplay();

        $this->assertStringMatchesFormatFile(__DIR__ . '/Fixtures/FailingTests/' . $expectedOutputFile, $output);
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    /**
     * @return array[]
     */
    public function provideExpectedFailingTestOutput(): array
    {
        return [
            [OutputInterface::VERBOSITY_NORMAL, 'expected-normal-output.txt'],
            [OutputInterface::VERBOSITY_VERBOSE, 'expected-verbose-output.txt'],
            [OutputInterface::VERBOSITY_VERY_VERBOSE, 'expected-very-verbose-output.txt'],
            [OutputInterface::VERBOSITY_DEBUG, 'expected-debug-output.txt'],
        ];
    }

    public function testShouldExitWithCode0EvenWithFailedTestsWhenNoExitOptionIsPassed(): void
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'chrome',
                '--no-exit' => true,
                '--tests-dir' => __DIR__ . '/Fixtures/FailingTests',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldNotStartBrowserForSkippedTests(): void
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'chrome',
                '--tests-dir' => __DIR__ . '/Fixtures/SkippedTests',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $this->assertSame(1, mb_substr_count($this->tester->getDisplay(), 'Initializing "chrome" WebDriver'));
    }
}
