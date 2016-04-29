<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Process\ProcessWrapper;
use Lmc\Steward\Publisher\AbstractPublisher;
use Lmc\Steward\Publisher\XmlPublisher;
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
    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('results')
            ->setDescription('Show test results overview')
            ->addOption(
                RunCommand::OPTION_LOGS_DIR,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to directory with results.xml file',
                realpath(STEWARD_BASE_DIR . '/logs')
            );

        $this->getDispatcher()->dispatch(CommandEvents::CONFIGURE, new BasicConsoleEvent($this));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filePath = $input->getOption(RunCommand::OPTION_LOGS_DIR) . '/' . XmlPublisher::FILE_NAME;

        if (!is_readable($filePath)) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot read results file "%s", make sure it is accessible '
                    . '(or use --logs-dir option if it is stored elsewhere)',
                    $filePath
                )
            );
        }

        $data = $this->processResults($filePath);

        $this->outputTable($output, $data['testcases'], $output->isDebug());

        return 0;
    }

    /**
     * Process results file to simple virtual data structure
     *
     * @param string $filePath
     * @return array
     */
    private function processResults($filePath)
    {
        $xml = simplexml_load_string(file_get_contents($filePath));

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
                'start' => $testcase['start'] ? new \DateTimeImmutable($testcase['start']) : null,
                'end' => $testcase['end'] ? new \DateTimeImmutable($testcase['end']) : null,
                'tests' => [],
            ];

            foreach ($testcase->test as $test) {
                $testData = [
                    'name' => (string) $test['name'],
                    'status' => (string) $test['status'],
                    'result' => (string) $test['result'],
                    'start' => $test['start'] ? new \DateTimeImmutable($test['start']) : null,
                    'end' => $test['end'] ? new \DateTimeImmutable($test['end']) : null,
                ];

                $testcaseData['tests'][] = $testData;
            }

            $data['testcases'][] = $testcaseData;
        }

        return $data;
    }

    /**
     * @param OutputInterface $output
     * @param array $data
     * @param bool $showTests
     */
    private function outputTable(OutputInterface $output, array $data, $showTests)
    {
        $table = new Table($output);
        $rightAlignedColumn = new TableStyle();
        $rightAlignedColumn->setPadType(STR_PAD_LEFT);

        $testcaseColumnHeader = new TableCell($showTests ? 'Testcase / test' : 'Testcase');
        $header = [$testcaseColumnHeader, 'Status', 'Result', 'Start time', 'End time', 'Duration'];

        $table->setHeaders($header);
        $table->setColumnStyle(5, $rightAlignedColumn);

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
                    if ($test['status'] == AbstractPublisher::TEST_STATUS_STARTED
                        && $tc['result'] == ProcessWrapper::PROCESS_RESULT_FATAL
                    ) {
                        $hasParentTestcaseFataled = true;
                    }

                    $isInProgress = false;
                    if (!$hasParentTestcaseFataled && $test['status'] == AbstractPublisher::TEST_STATUS_STARTED) {
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

    /**
     * @param \DateTimeInterface|null $start
     * @param \DateTimeInterface|null $end
     * @param bool $inProgress
     * @return string
     */
    private function formatDiff($start, $end, $inProgress = false)
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

    /**
     * @param \DateTimeInterface|null $date
     * @return string
     */
    private function formatDate($date)
    {
        $output = '';
        if ($date instanceof \DateTimeImmutable) {
            $output = $date->format('Y-m-d G:i:s');
        }

        return $output;
    }

    /**
     * @param string $status
     * @param string $result
     * @return string
     */
    private function formatTestcaseResult($status, $result)
    {
        $output = $result;

        if ($status != ProcessWrapper::PROCESS_STATUS_DONE) {
            return $output;
        }

        $colorMap = [
            ProcessWrapper::PROCESS_RESULT_PASSED => '<fg=green>',
            ProcessWrapper::PROCESS_RESULT_FAILED => '<fg=red>',
            ProcessWrapper::PROCESS_RESULT_FATAL => '<fg=yellow>',
        ];

        if (isset($colorMap[$result])) {
            $output = $colorMap[$result] . $output . '</>';
        }

        return $output;
    }

    /**
     * @param string $result
     * @param string $status
     * @param string $parentTestcaseResult
     * @return string
     */
    private function formatTestResult($result, $status, $parentTestcaseResult)
    {
        $output = $result;

        if ($parentTestcaseResult == ProcessWrapper::PROCESS_RESULT_FATAL
            && $status == AbstractPublisher::TEST_STATUS_STARTED
        ) {
            return '<fg=yellow>fatal</>';
        }

        $colorMap = [
            AbstractPublisher::TEST_RESULT_PASSED => '<fg=green>',
            AbstractPublisher::TEST_RESULT_FAILED => '<fg=red>',
            AbstractPublisher::TEST_RESULT_BROKEN => '<fg=red>',
            AbstractPublisher::TEST_RESULT_SKIPPED => '<fg=magenta>',
            AbstractPublisher::TEST_RESULT_INCOMPLETE => '<fg=magenta>',

        ];

        if (isset($colorMap[$result])) {
            $output = $colorMap[$result] . $output . '</>';
        }

        return $output;
    }
}
