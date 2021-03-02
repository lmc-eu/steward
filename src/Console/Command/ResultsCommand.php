<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Exception\CommandException;
use Lmc\Steward\Process\ProcessWrapper;
use Lmc\Steward\Publisher\AbstractPublisher;
use Lmc\Steward\Publisher\XmlPublisher;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Show results of the latest test run (according to the results.xml file)
 */
class ResultsCommand extends Command
{
    public const OPTION_RESULTS_FILE = 'results-file';

    /** @var array */
    protected const TESTCASE_COLOR_MAP = [
        ProcessWrapper::PROCESS_RESULT_PASSED => '<fg=green>',
        ProcessWrapper::PROCESS_RESULT_FAILED => '<fg=red>',
        ProcessWrapper::PROCESS_RESULT_FATAL => '<fg=yellow>',
    ];
    /** @var array */
    protected const TEST_COLOR_MAP = [
        AbstractPublisher::TEST_RESULT_PASSED => '<fg=green>',
        AbstractPublisher::TEST_RESULT_FAILED => '<fg=red>',
        AbstractPublisher::TEST_RESULT_BROKEN => '<fg=red>',
        AbstractPublisher::TEST_RESULT_SKIPPED => '<fg=magenta>',
        AbstractPublisher::TEST_RESULT_INCOMPLETE => '<fg=magenta>',
    ];

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('results')
            ->setDescription('Show test results overview')
            ->addOption(
                self::OPTION_RESULTS_FILE,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to test results xml file',
                realpath(STEWARD_BASE_DIR . '/logs/' . XmlPublisher::FILE_NAME)
            );

        $this->getDispatcher()->dispatch(new BasicConsoleEvent($this), CommandEvents::CONFIGURE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getOption(self::OPTION_RESULTS_FILE);

        if (!is_readable($filePath)) {
            throw CommandException::forNotAccessibleResultsFile($filePath, self::OPTION_RESULTS_FILE);
        }

        $data = $this->processResults($filePath);

        $this->outputTable($output, $data['testcases'], $output->isDebug());
        $this->outputFooter($output, $data['stats']);

        return 0;
    }

    /**
     * Process results file to simple virtual data structure
     */
    private function processResults(string $filePath): array
    {
        $xml = simplexml_load_file($filePath);

        $data = [
            'testcases' => [],
            'stats' => [
                'tcCount' => count($xml->xpath('//testcase')),
                'tcPreparedCount' => count($xml->xpath('//testcase[@status=\'prepared\']')),
                'tcQueuedCount' => count($xml->xpath('//testcase[@status=\'queued\']')),
                'tcDoneCount' => count($xml->xpath('//testcase[@status=\'done\']')),
                'tcPassedCount' => count($xml->xpath('//testcase[@status=\'done\' and @result=\'passed\']')),
                'tcFailedCount' => count($xml->xpath('//testcase[@status=\'done\' and @result=\'failed\']')),
                'tcFatalCount' => count($xml->xpath('//testcase[@status=\'done\' and @result=\'fatal\']')),

                'testCount' => count($xml->xpath('//test')),
                'testStartedCount' => count($xml->xpath('//test[@status=\'started\']')),
                'testDoneCount' => count($xml->xpath('//test[@status=\'done\']')),
                'testPassedCount' => count($xml->xpath('//test[@status=\'done\' and @result=\'passed\']')),
                'testFailedBrokenCount' =>
                    count($xml->xpath('//test[@status=\'done\' and (@result=\'failed\' or @result=\'broken\')]')),
                'testSkippedIncompleteCount' =>
                    count($xml->xpath('//test[@status=\'done\' and (@result=\'skipped\' or @result=\'incomplete\')]')),
            ],
        ];

        $testcases = $xml->xpath('//testcases/testcase');
        foreach ($testcases as $testcase) {
            $testcaseData = [
                'name' => (string) $testcase['name'],
                'status' => (string) $testcase['status'],
                'result' => (string) $testcase['result'],
                'start' => $testcase['start'] ? new \DateTimeImmutable((string) $testcase['start']) : null,
                'end' => $testcase['end'] ? new \DateTimeImmutable((string) $testcase['end']) : null,
                'tests' => [],
            ];

            foreach ($testcase->test as $test) {
                $testData = [
                    'name' => (string) $test['name'],
                    'status' => (string) $test['status'],
                    'result' => (string) $test['result'],
                    'start' => $test['start'] ? new \DateTimeImmutable((string) $test['start']) : null,
                    'end' => $test['end'] ? new \DateTimeImmutable((string) $test['end']) : null,
                ];

                $testcaseData['tests'][] = $testData;
            }

            $data['testcases'][] = $testcaseData;
        }

        return $data;
    }

    private function outputFooter(OutputInterface $output, array $data): void
    {
        $isFinished = ($data['tcCount'] === $data['tcDoneCount']);

        $output->writeln(
            sprintf(
                'Testcases (%d total): prepared: %d, running: %d, done: %d (passed: %d, failed: %d, fatal: %d)',
                $data['tcCount'],
                $data['tcPreparedCount'],
                $data['tcQueuedCount'],
                $data['tcDoneCount'],
                $data['tcPassedCount'],
                $data['tcFailedCount'],
                $data['tcFatalCount']
            )
        );

        $output->writeln(
            sprintf(
                'Tests (%d %s): started: %d, done: %d (passed: %d, failed or broken: %d, skipped or incomplete: %d)',
                $data['testCount'],
                $isFinished ? 'total' : 'so far',
                $data['testStartedCount'],
                $data['testDoneCount'],
                $data['testPassedCount'],
                $data['testFailedBrokenCount'],
                $data['testSkippedIncompleteCount']
            )
        );

        $progressBar = new ProgressBar($output, $data['tcCount']);
        $progressBar->setFormat('%current%/%max% testcases done [%bar%] %percent:3s%%');
        $progressBar->setOverwrite(false);
        $progressBar->setBarWidth(70); // minimal table width, TODO: adjust after setColumnWidths is used in Symfony 3.1
        $progressBar->setProgress($data['tcDoneCount']);

        $output->writeln('');
    }

    private function outputTable(OutputInterface $output, array $data, bool $showTests): void
    {
        $table = new Table($output);
        $rightAlignedColumn = new TableStyle();
        $rightAlignedColumn->setPadType(STR_PAD_LEFT);

        $testcaseColumnHeader = new TableCell($showTests ? 'Testcase / test' : 'Testcase');
        $header = [$testcaseColumnHeader, 'Status', 'Result', 'Start time', 'End time', 'Duration'];

        $table->setHeaders($header);
        $table->setColumnStyle(5, $rightAlignedColumn);
        // TODO in Symfony 3.1 use setColumnWidths to set minimal columns width (and also increase barWidth accordingly)

        $first = true;
        foreach ($data as $tc) {
            if (!$first && $showTests) {
                $table->addRow(new TableSeparator());
            }
            if ($first) {
                $first = false;
            }

            $table->addRow([
                $tc['name'],
                $tc['status'],
                $this->formatTestcaseResult($tc['status'], $tc['result']),
                $this->formatDate($tc['start']),
                $this->formatDate($tc['end']),
                $this->formatDiff($tc['start'], $tc['end']),
            ]);

            if ($showTests) {
                foreach ($tc['tests'] as $test) {
                    $hasParentTestcaseFataled = false;
                    if ($test['status'] === AbstractPublisher::TEST_STATUS_STARTED
                        && $tc['result'] === ProcessWrapper::PROCESS_RESULT_FATAL
                    ) {
                        $hasParentTestcaseFataled = true;
                    }

                    $isInProgress = false;
                    if (!$hasParentTestcaseFataled && $test['status'] === AbstractPublisher::TEST_STATUS_STARTED) {
                        $isInProgress = true;
                    }

                    $table->addRow([
                        new TableCell(' - ' . $test['name']),
                        $hasParentTestcaseFataled ? '' : $test['status'],
                        $this->formatTestResult($test['result'], $test['status'], $tc['result']),
                        $this->formatDate($test['start']),
                        $hasParentTestcaseFataled ? '-' : $this->formatDate($test['end']),
                        $this->formatDiff($test['start'], $test['end'], $isInProgress),
                    ]);
                }
            }
        }

        $table->render();
    }

    private function formatDiff(?\DateTimeInterface $start, ?\DateTimeInterface $end, $inProgress = false): string
    {
        $output = '';

        if ($inProgress && !$end) { // It is in progress and end time is not available, use current time
            $end = new \DateTimeImmutable();
        }

        if ($start instanceof \DateTimeInterface && $end instanceof \DateTimeInterface) {
            $output = $end->getTimestamp() - $start->getTimestamp();

            $output .= ' ' . ($inProgress ? '<options=blink>sec</>' : 'sec');
        }

        return $output;
    }

    private function formatDate(?\DateTimeInterface $date): string
    {
        $output = '';
        if ($date instanceof \DateTimeImmutable) {
            $output = $date->format('Y-m-d G:i:s');
        }

        return $output;
    }

    private function formatTestcaseResult(string $status, string $result): string
    {
        $output = $result;

        if ($status !== ProcessWrapper::PROCESS_STATUS_DONE) {
            return $output;
        }

        if (isset(self::TESTCASE_COLOR_MAP[$result])) {
            $output = self::TESTCASE_COLOR_MAP[$result] . $output . '</>';
        }

        return $output;
    }

    private function formatTestResult(string $result, string $status, string $parentTestcaseResult): string
    {
        $output = $result;

        if ($parentTestcaseResult === ProcessWrapper::PROCESS_RESULT_FATAL
            && $status === AbstractPublisher::TEST_STATUS_STARTED
        ) {
            return self::TESTCASE_COLOR_MAP[ProcessWrapper::PROCESS_RESULT_FATAL] . 'fatal</>';
        }

        if (isset(self::TEST_COLOR_MAP[$result])) {
            $output = self::TEST_COLOR_MAP[$result] . $output . '</>';
        }

        return $output;
    }
}
