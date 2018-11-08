<?php declare(strict_types=1);

namespace Lmc\Steward\Process;

use Lmc\Steward\Console\Style\StewardStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ExecutionLoop
{
    /** @var ProcessSet */
    private $processSet;
    /** @var StewardStyle */
    private $io;
    /** @var OptimizeOrderInterface */
    private $optimizeStrategy;
    /** @var int */
    private $parallelLimit;
    /** @var Stopwatch */
    protected $stopwatch;

    public function __construct(
        ProcessSet $processSet,
        StewardStyle $io,
        OptimizeOrderInterface $optimizeStrategy,
        int $parallelLimit = 50
    ) {
        $this->processSet = $processSet;
        $this->io = $io;
        $this->optimizeStrategy = $optimizeStrategy;
        $this->parallelLimit = $parallelLimit;
        $this->stopwatch = new Stopwatch();
    }

    public function start(): bool
    {
        $this->initialize();

        $this->io->isVeryVerbose() ? $this->io->section('Starting execution of testcases') : $this->io->newLine();

        $counterWaitingOutput = 1;
        $statusesCountLast = [];

        // Iterate over prepared and queued until everything is done
        while (true) {
            $prepared = $this->processSet->get(ProcessWrapper::PROCESS_STATUS_PREPARED);
            $queued = $this->processSet->get(ProcessWrapper::PROCESS_STATUS_QUEUED);

            if (count($prepared) === 0 && count($queued) === 0) {
                break;
            }
            // Start all prepared tasks and set status of not running as finished
            foreach ($prepared as $testClass => $processWrapper) {
                if (!$processWrapper->getProcess()->isStarted()) {
                    if ($this->io->isVeryVerbose()) {
                        $this->io->runStatus(
                            sprintf(
                                'Execution of testcase "%s" started%s',
                                $testClass,
                                $this->io->isDebug() ?
                                    " with command:\n" . $processWrapper->getProcess()->getCommandLine() : ''
                            )
                        );
                    }

                    $this->stopwatch->start($testClass);
                    $processWrapper->getProcess()->start();
                    usleep(50000); // wait for a while (0,05 sec) to let processes be started in intended order

                    continue;
                }

                if ($timeoutError = $processWrapper->checkProcessTimeout()) {
                    if ($this->io->isVeryVerbose()) {
                        $this->io->error($timeoutError);
                    }
                }

                if ($this->io->isDebug()) { // In debug mode print all output as it comes
                    $this->flushProcessOutput($processWrapper);
                }

                if (!$processWrapper->getProcess()->isRunning()) {
                    $testcaseEnd = $this->stopwatch->stop($testClass);
                    // Mark no longer running processes as finished
                    $processWrapper->setStatus(ProcessWrapper::PROCESS_STATUS_DONE);

                    $hasProcessPassed = $processWrapper->getResult() === ProcessWrapper::PROCESS_RESULT_PASSED;

                    if ($this->io->isDebug()) { // There could be new output since the previous flush
                        $this->flushProcessOutput($processWrapper);
                    }

                    if ($this->io->isVeryVerbose()) {
                        $processOutput = $processErrorOutput = '';
                        if (!$hasProcessPassed) { // If process failed, collect its output
                            $processOutput = $processWrapper->getProcess()->getIncrementalOutput();
                            $processErrorOutput = $processWrapper->getProcess()->getIncrementalErrorOutput();
                        }

                        $testcaseFinishedMessage = sprintf(
                            'Finished execution of testcase "%s" (result: %s, time: %.1F sec)%s',
                            $testClass,
                            $processWrapper->getResult(),
                            $testcaseEnd->getDuration() / 1000,
                            (!empty($processOutput) || !empty($processErrorOutput) ? ', output:' : '')
                        );
                        $hasProcessPassed ? $this->io->runStatusSuccess($testcaseFinishedMessage)
                            : $this->io->runStatusError($testcaseFinishedMessage);

                        $this->io->output($processOutput, $processWrapper->getClassName());
                        $this->io->errorOutput($processErrorOutput, $processWrapper->getClassName());
                    } elseif ($this->io->isVerbose() && !$hasProcessPassed) {
                        $this->io->runStatusError(
                            sprintf('Testcase "%s" %s', $testClass, $processWrapper->getResult())
                        );
                    }

                    // Fail also process dependencies
                    if (!$hasProcessPassed) {
                        $this->failDependants($testClass);
                    }
                }
            }
            $this->dequeueParallelProcesses();
            $this->dequeueDependentProcesses();

            $statusesCount = $this->processSet->countStatuses();

            // if the output didn't change, wait 100 iterations (10 seconds) before printing it again
            if ($statusesCount === $statusesCountLast && $counterWaitingOutput % 100 !== 0) {
                $counterWaitingOutput++;
            } else {
                $this->printExecutionLoopStatus($statusesCount);
                $counterWaitingOutput = 1;
            }

            $statusesCountLast = $statusesCount;
            usleep(100000); // 0,1 sec
        }

        $doneCount = count($this->processSet->get(ProcessWrapper::PROCESS_STATUS_DONE));
        $resultsCount = $this->processSet->countResults();
        $allTestsPassed = ($resultsCount[ProcessWrapper::PROCESS_RESULT_PASSED] === $doneCount);
        $resultsInfo = [];
        foreach (ProcessWrapper::PROCESS_RESULTS as $resultType) {
            if ($resultsCount[$resultType] > 0) {
                $resultsInfo[] = sprintf('%s: %d', $resultType, $resultsCount[$resultType]);
            }
        }

        $event = $this->stopwatch->stop('run');
        $this->io->runStatus(sprintf('All testcases done in %.1F seconds', $event->getDuration() / 1000));

        $resultMessage = sprintf('Testcases executed: %d (%s)', $doneCount, implode(', ', $resultsInfo));
        $allTestsPassed ? $this->io->success($resultMessage) : $this->io->error($resultMessage);

        return $allTestsPassed;
    }

    protected function initialize(): void
    {
        $this->stopwatch->start('run');

        // Optimize processes order
        $this->processSet->optimizeOrder($this->optimizeStrategy);

        // Initialize first processes that should be run
        $this->dequeueProcessesWithoutDelay();
    }

    /**
     * Set queued processes without delay as prepared
     */
    protected function dequeueProcessesWithoutDelay(): void
    {
        $queuedProcesses = $this->processSet->get(ProcessWrapper::PROCESS_STATUS_QUEUED);

        foreach ($queuedProcesses as $className => $processWrapper) {
            if ($processWrapper->isDelayed()) {
                $this->io->writeln(
                    sprintf(
                        'Testcase "%s" is queued to be run %01.1f minutes after testcase "%s" is finished',
                        $className,
                        $processWrapper->getDelayMinutes(),
                        $processWrapper->getDelayAfter()
                    ),
                    OutputInterface::VERBOSITY_DEBUG
                );
            } elseif ($this->parallelLimitReached()) {
                $this->io->writeln(
                    sprintf('Max parallel limit reached, testcase "%s" is queued', $className),
                    OutputInterface::VERBOSITY_QUIET
                );
            } else {
                $this->io->writeln(
                    sprintf('Testcase "%s" is prepared to be run', $className),
                    OutputInterface::VERBOSITY_DEBUG
                );
                $processWrapper->setStatus(ProcessWrapper::PROCESS_STATUS_PREPARED);
            }
        }
    }

    protected function dequeueDependentProcesses(): void
    {
        // Retrieve names of done tests
        $done = $this->processSet->get(ProcessWrapper::PROCESS_STATUS_DONE);
        $doneClasses = [];
        foreach ($done as $testClass => $processWrapper) {
            $doneClasses[] = $testClass;
        }

        // Set queued tasks as prepared if their dependent task is done and delay has passed
        $queued = $this->processSet->get(ProcessWrapper::PROCESS_STATUS_QUEUED);
        foreach ($queued as $testClass => $processWrapper) {
            $delaySeconds = $processWrapper->getDelayMinutes() * 60;

            if (!$this->parallelLimitReached()
                && in_array($processWrapper->getDelayAfter(), $doneClasses, true)
                && (time() - $done[$processWrapper->getDelayAfter()]->getFinishedTime()) > $delaySeconds
            ) {
                if ($this->io->isVeryVerbose()) {
                    $this->io->runStatus(sprintf('Dequeing testcase "%s"', $testClass));
                }
                $processWrapper->setStatus(ProcessWrapper::PROCESS_STATUS_PREPARED);
            }
        }
    }

    protected function dequeueParallelProcesses(): void
    {
        $queued = $this->processSet->get(ProcessWrapper::PROCESS_STATUS_QUEUED);

        foreach ($queued as $testClass => $processWrapper) {
            if (!$processWrapper->isDelayed() && !$this->parallelLimitReached()) {
                if ($this->io->isVeryVerbose()) {
                    $this->io->runStatus(
                        sprintf('Dequeing testcase "%s" which was queued because of parallel limit', $testClass)
                    );
                }
                $processWrapper->setStatus(ProcessWrapper::PROCESS_STATUS_PREPARED);
            }
        }
    }

    protected function printExecutionLoopStatus(array $statusesCount): void
    {
        $resultsInfo = [];
        $resultsCount = $this->processSet->countResults();
        if ($statusesCount[ProcessWrapper::PROCESS_STATUS_DONE] > 0) {
            foreach (ProcessWrapper::PROCESS_RESULTS as $resultType) {
                if ($resultsCount[$resultType] > 0) {
                    $resultsInfo[] = sprintf(
                        '%s: <fg=%s>%d</>',
                        $resultType,
                        $resultType === ProcessWrapper::PROCESS_RESULT_PASSED ? 'green' : 'red',
                        $resultsCount[$resultType]
                    );
                }
            }
        }

        $this->io->runStatus(
            sprintf(
                'Waiting (running: %d, queued: %d, done: %d%s)',
                $statusesCount[ProcessWrapper::PROCESS_STATUS_PREPARED],
                $statusesCount[ProcessWrapper::PROCESS_STATUS_QUEUED],
                $statusesCount[ProcessWrapper::PROCESS_STATUS_DONE],
                count($resultsInfo) ? ' [' . implode(', ', $resultsInfo) . ']' : ''
            )
        );
    }

    protected function failDependants(string $testClass): void
    {
        $failedDependants = $this->processSet->failDependants($testClass);

        if ($this->io->isVerbose()) {
            foreach ($failedDependants as $failedClass => $failedProcessWrapper) {
                $this->io->runStatusError(
                    sprintf(
                        'Failing testcase "%s", because it was depending on failed "%s"',
                        $failedClass,
                        $testClass
                    )
                );
            }
        }
    }

    /**
     * Flush output of the process
     */
    protected function flushProcessOutput(ProcessWrapper $processWrapper): void
    {
        $this->io->output(
            $processWrapper->getProcess()->getIncrementalOutput(),
            $processWrapper->getClassName()
        );
        $this->io->errorOutput(
            $processWrapper->getProcess()->getIncrementalErrorOutput(),
            $processWrapper->getClassName()
        );
    }

    protected function parallelLimitReached(): bool
    {
        return count($this->processSet->get(ProcessWrapper::PROCESS_STATUS_PREPARED)) >= $this->parallelLimit;
    }
}
