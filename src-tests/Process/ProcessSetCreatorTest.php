<?php

namespace Lmc\Steward\Process;

use Lmc\Steward\Console\Command\RunTestsCommand;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use Lmc\Steward\Publisher\AbstractPublisher;
use Lmc\Steward\Publisher\XmlPublisher;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * @covers Lmc\Steward\Process\ProcessSetCreator
 */
class ProcessSetCreatorTest extends \PHPUnit_Framework_TestCase
{
    /** @var EventDispatcher|\PHPUnit_Framework_MockObject_MockObject */
    protected $dispatcherMock;
    /** @var RunTestsCommand|\PHPUnit_Framework_MockObject_MockObject */
    protected $command;
    /** @var InputInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $input;
    /** @var BufferedOutput */
    protected $bufferedOutput;
    /** @var ProcessSetCreator */
    protected $creator;
    /** @var AbstractPublisher|\PHPUnit_Framework_MockObject_MockObject */
    protected $publisherMock;

    // Fully classified names of dummy tests
    const NAME_DUMMY_TEST = 'Lmc\Steward\Process\Fixtures\DummyTests\DummyTest';
    const NAME_BAR_TEST = 'Lmc\Steward\Process\Fixtures\DummyTests\GroupBarTest';
    const NAME_FOO_TEST = 'Lmc\Steward\Process\Fixtures\DummyTests\GroupFooTest';

    public function setUp()
    {
        $this->dispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();

        $this->command = new RunTestsCommand($this->dispatcherMock);

        $this->input = new StringInput('staging firefox');
        $this->input->bind($this->command->getDefinition());

        $this->bufferedOutput = new BufferedOutput();
        $this->bufferedOutput->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        $this->bufferedOutput->setDecorated(false);

        $this->publisherMock = $this->getMockBuilder(XmlPublisher::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->creator = new ProcessSetCreator(
            $this->command,
            $this->input,
            $this->bufferedOutput,
            $this->publisherMock
        );
    }

    public function testShouldCreateEmptyProcessSetIfNoFilesGiven()
    {
        $finderMock = $this->getMockBuilder(Finder::class)
            ->setMethods(['getIterator'])
            ->getMock();

        $finderMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \AppendIterator());

        $processSet = $this->creator->createFromFiles($finderMock, [], []);

        $this->assertInstanceOf(ProcessSet::class, $processSet);
        $this->assertCount(0, $processSet);
    }

    public function testShouldCreateProcessSetFromGivenFiles()
    {
        $processSet = $this->creator->createFromFiles($this->findDummyTests(), [], []);

        $this->assertQueuedTests([self::NAME_DUMMY_TEST, self::NAME_BAR_TEST, self::NAME_FOO_TEST], $processSet);

        // Test properties of DummyTest
        /** @var Process $dummyTestProcess */
        $processes = $processSet->get(ProcessSet::PROCESS_STATUS_QUEUED);
        $dummyTestProcess = $processes[self::NAME_DUMMY_TEST]->process;
        $testCommand = $dummyTestProcess->getCommandLine();
        $testEnv = $dummyTestProcess->getEnv();

        $this->assertContains('phpunit', $testCommand);
        $this->assertContains('--log-junit=logs/Lmc-Steward-Process-Fixtures-DummyTests-DummyTest.xml', $testCommand);
        $this->assertNotContains('--colors', $testCommand); // Decorated output is disabled in setUp()
        $this->assertRegExp('/--configuration=.*\/src\/phpunit\.xml/', $testCommand);

        // Check defaults were passed to the Processes
        $definition = $this->command->getDefinition();
        $expectedEnv = [
            'BROWSER_NAME' => 'firefox',
            'ENV' => 'staging',
            'SERVER_URL' => $definition->getOption(RunTestsCommand::OPTION_SERVER_URL)->getDefault(),
            'PUBLISH_RESULTS' => 0,
            'FIXTURES_DIR' => $definition->getOption(RunTestsCommand::OPTION_FIXTURES_DIR)->getDefault(),
            'LOGS_DIR' =>  $definition->getOption(RunTestsCommand::OPTION_LOGS_DIR)->getDefault(),
        ];
        $this->assertArraySubset($expectedEnv, $testEnv);
    }

    public function testShouldOnlyAddTestsOfGivenGroups()
    {
        $processSet = $this->creator->createFromFiles($this->findDummyTests(), ['bar', 'foo'], []);

        $this->assertQueuedTests([self::NAME_BAR_TEST, self::NAME_FOO_TEST], $processSet);

        $output = $this->bufferedOutput->fetch();
        $this->assertContains('by group(s): bar, foo', $output);
        $this->assertRegExp('/Found testcase file #1 in group bar: .*\/GroupBarTest\.php/', $output);
        $this->assertRegExp('/Found testcase file #2 in group foo: .*\/GroupFooTest\.php/', $output);
    }

    public function testShouldExcludeTestsOfGivenGroups()
    {
        $processSet = $this->creator->createFromFiles($this->findDummyTests(), [], ['bar', 'foo']);

        $this->assertQueuedTests([self::NAME_DUMMY_TEST], $processSet);

        $output = $this->bufferedOutput->fetch();
        $this->assertContains('excluding group(s): bar, foo', $output);
        $this->assertRegExp('/Excluding testcase file .*\/GroupBarTest\.php with group bar/', $output);
        $this->assertRegExp('/Excluding testcase file .*\/GroupFooTest\.php with group foo/', $output);
    }

    public function testShouldAddTestsOfGivenGroupsButExcludeFromThemThoseOfExcludedGroups()
    {
        // group "both" gets included (incl. GroupFooTest and GroupBarTest), but "GroupBarTest" gets excluded
        $processSet = $this->creator->createFromFiles($this->findDummyTests(), ['both'], ['bar']);

        $this->assertQueuedTests([self::NAME_FOO_TEST], $processSet);

        $output = $this->bufferedOutput->fetch();
        $this->assertContains('by group(s): both', $output);
        $this->assertContains('excluding group(s): bar', $output);
        $this->assertRegExp('/Found testcase file #1 in group both: .*\/GroupFooTest\.php/', $output);
        $this->assertRegExp('/Excluding testcase file .*\/GroupBarTest\.php with group bar/', $output);
    }

    public function testShouldPropagateCustomOptionsIntoProcess()
    {
        $this->input = new StringInput(
            'trolling chrome'
            . ' --' . RunTestsCommand::OPTION_SERVER_URL .'=http://foo.bar:1337'
            . ' --' . RunTestsCommand::OPTION_FIXTURES_DIR .'=custom-fixtures-dir/'
            . ' --' . RunTestsCommand::OPTION_LOGS_DIR .'=custom-logs-dir/'
            . ' --' . RunTestsCommand::OPTION_PUBLISH_RESULTS
        );

        $this->input->bind($this->command->getDefinition());

        // Redeclare creator so it uses the new input
        $this->creator = new ProcessSetCreator(
            $this->command,
            $this->input,
            $this->bufferedOutput,
            $this->publisherMock
        );

        $files = $this->findDummyTests('DummyTest.php'); // find only one file (we don't need more for the test)

        $processSet = $this->creator->createFromFiles($files, [], []);

        /** @var Process $process */
        $process = $processSet->get(ProcessSet::PROCESS_STATUS_QUEUED)[self::NAME_DUMMY_TEST]->process;
        $processEnv = $process->getEnv();

        $this->assertArraySubset(
            [
                'BROWSER_NAME' => 'chrome',
                'ENV' => 'trolling',
                'SERVER_URL' => 'http://foo.bar:1337',
                'PUBLISH_RESULTS' => '1',
                'FIXTURES_DIR' => 'custom-fixtures-dir/',
                'LOGS_DIR' => 'custom-logs-dir/',
            ],
            $processEnv
        );
    }

    public function testShouldSetPHPUnitColoredOptionOnlyIfTheOutputIsDecorated()
    {
        $files = $this->findDummyTests('DummyTest.php'); // find only one file (we don't need more for the test)

        // Test default commands (decorated output was disabled in setUp)
        $processSet = $this->creator->createFromFiles($files, [], []);
        /** @var Process $process */
        $process = $processSet->get(ProcessSet::PROCESS_STATUS_QUEUED)[self::NAME_DUMMY_TEST]->process;
        $commandWithoutColors = $process->getCommandLine();
        $this->assertNotContains('--colors', $commandWithoutColors);

        // Enable decorated output and test if the option is added to the command
        $this->bufferedOutput->setDecorated(true);
        $this->creator = new ProcessSetCreator(
            $this->command,
            $this->input,
            $this->bufferedOutput,
            $this->publisherMock
        );

        $processSet = $this->creator->createFromFiles($files, [], []);
        /** @var Process $process */
        $process = $processSet->get(ProcessSet::PROCESS_STATUS_QUEUED)[self::NAME_DUMMY_TEST]->process;
        $commandWithColors = $process->getCommandLine();
        $this->assertContains('--colors=always', $commandWithColors);
    }

    public function testShouldDispatchProcessEvent()
    {
        $this->dispatcherMock->expects($this->at(0))
            ->method('dispatch')
            ->with($this->equalTo(CommandEvents::RUN_TESTS_PROCESS), $this->isInstanceOf(RunTestsProcessEvent::class));

        $this->creator->createFromFiles($this->findDummyTests(), [], ['bar', 'foo']);
    }

    /**
     * @param string $pattern
     * @return Finder
     */
    protected function findDummyTests($pattern = '*Test.php')
    {
        return $files = (new Finder())
            ->useBestAdapter()
            ->files()
            ->in(__DIR__ . '/Fixtures/DummyTests')
            ->name($pattern);
    }

    /**
     * Assert ProcessSet consists only of tests of expected names
     * @param array $expectedTestNames
     * @param ProcessSet $processSet
     */
    protected function assertQueuedTests(array $expectedTestNames, $processSet)
    {
        $this->assertInstanceOf(ProcessSet::class, $processSet);
        $this->assertCount(count($expectedTestNames), $processSet);
        $processes = $processSet->get(ProcessSet::PROCESS_STATUS_QUEUED);

        foreach ($expectedTestNames as $expectedTestName) {
            $this->assertArrayHasKey($expectedTestName, $processes);
        }
    }
}
