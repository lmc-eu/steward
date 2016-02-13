<?php

namespace Lmc\Steward\Process;

use Graphp\Algorithms\Tree\OutTree;
use Fhaculty\Graph\Graph;
use Lmc\Steward\Publisher\AbstractPublisher;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Set of Test processes.
 */
class ProcessSet implements \Countable
{
    /**
     * Array of objects with test processes, indexed by testcase fully qualified name
     * @var array
     */
    protected $processes = [];

    /** @var AbstractPublisher */
    protected $publisher;

    /** @var Graph */
    protected $graph;

    /** @var OutTree */
    protected $tree;

    /** Process prepared to be run */
    const PROCESS_STATUS_PREPARED = 'prepared';
    /** Process in queue  - waiting to be prepared */
    const PROCESS_STATUS_QUEUED = 'queued';
    /** Finished process */
    const PROCESS_STATUS_DONE = 'done';

    /** Process passed successful (with all its tests passing) */
    const PROCESS_RESULT_PASSED = 'passed';
    /** Process failed - some tests have failed or are broken */
    const PROCESS_RESULT_FAILED = 'failed';
    /** Process fatally failed (PHP fatal error occurred - eg. no WebDriver available) */
    const PROCESS_RESULT_FATAL = 'fatal';

    /** @var array List of possible process statuses */
    public static $processStatuses = [
        self::PROCESS_STATUS_PREPARED,
        self::PROCESS_STATUS_QUEUED,
        self::PROCESS_STATUS_DONE,
    ];

    /** @var array List of possible process results */
    public static $processResults = [
        self::PROCESS_RESULT_PASSED,
        self::PROCESS_RESULT_FAILED,
        self::PROCESS_RESULT_FATAL,
    ];

    /**
     * Instantiate processSet to manage processes in different states,
     * If publisher is passed, it is used to publish process statuses after status changes.
     * @param AbstractPublisher $publisher OPTIONAL
     */
    public function __construct(AbstractPublisher $publisher = null)
    {
        $this->publisher = $publisher;

        $this->graph = new Graph();
    }

    /**
     * @param AbstractPublisher $publisher
     */
    public function setPublisher(AbstractPublisher $publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * Get count of all processes in the set
     * @return int
     */
    public function count()
    {
        return count($this->processes);
    }

    /**
     * Add new process to the set.
     *
     * @param Process $process PHPUnit process to run
     * @param string $className Tested class fully qualified name
     * @param string $delayAfter Other fully qualified class name after which this test should be run.
     * If is set, $delayMinutes must also be specified.
     * @param float|null $delayMinutes Delay execution for $delayMinutes after $delayAfter test.
     */

    public function add(Process $process, $className, $delayAfter = '', $delayMinutes = null)
    {
        if (isset($this->processes[$className])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Testcase with name "%s" was already added, make sure you don\'t have duplicate class name.',
                    $className
                )
            );
        }

        if (!empty($delayAfter)) {
            if ($delayMinutes === null) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Testcase "%s" should run after "%s", but no delay was defined',
                        $className,
                        $delayAfter
                    )
                );
            } elseif (!is_numeric($delayMinutes) || (float) $delayMinutes < 0) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Delay defined in testcase "%s" must be greater than or equal 0, but "%s" was given',
                        $className,
                        $delayMinutes
                    )
                );
            }

            $delayMinutes = (float) $delayMinutes;
        }

        if ($delayMinutes !== null && empty($delayAfter)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Testcase "%s" has defined delay %d minutes, but does not have defined the testcase to run after',
                    $className,
                    $delayMinutes
                )
            );
        }

        $this->processes[$className] = (object) [
            'status' => self::PROCESS_STATUS_QUEUED,
            'result' => null,
            'process' => $process,
            'delayAfter' => $delayAfter,
            'delayMinutes' => $delayMinutes,
            'finishedTime' => null,
        ];

        $this->graph->createVertex($className);

        if ($this->publisher) {
            $this->publisher->publishResults($className, self::PROCESS_STATUS_QUEUED, null);
        }
    }

    /**
     * Get array of processes in the set having given status
     *
     * @param string $status {prepared, queued, done}
     *
     * @return array
     */
    public function get($status)
    {
        $return = [];
        foreach ($this->processes as $className => $processObject) {
            if ($processObject->status == $status) {
                $return[$className] = $processObject;
            }
        }

        return $return;
    }

    /**
     * Set status of given process
     * @param $className string
     * @param $status string
     * @throws \InvalidArgumentException
     */
    public function setStatus($className, $status)
    {
        if (!in_array($status, self::$processStatuses)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Process status must be one of "%s", but "%s" given',
                    join(', ', self::$processStatuses),
                    $status
                )
            );
        }
        $this->processes[$className]->status = $status;

        $result = null;
        if ($status == self::PROCESS_STATUS_DONE) {
            $result = $this->resolveResult($className);
            $this->processes[$className]->result = $result;
        }

        if ($this->publisher) {
            $this->publisher->publishResults($className, $status, $result);
        }
    }

    /**
     * Set queued processes without delay as prepared
     * @param OutputInterface $output If provided, list of dequeued and queued processes will be printed
     */
    public function dequeueProcessesWithoutDelay(OutputInterface $output)
    {
        $queuedProcesses = $this->get(self::PROCESS_STATUS_QUEUED);
        foreach ($queuedProcesses as $className => $processObject) {
            if ($processObject->delayMinutes === null) {
                $output->writeln(
                    sprintf('Testcase "%s" is prepared to be run', $className),
                    OutputInterface::VERBOSITY_DEBUG
                );
                $this->setStatus($className, self::PROCESS_STATUS_PREPARED);
            } else {
                $output->writeln(
                    sprintf(
                        'Testcase "%s" is queued to be run %01.1f minutes after testcase "%s" is finished',
                        $className,
                        $processObject->delayMinutes,
                        $processObject->delayAfter
                    ),
                    OutputInterface::VERBOSITY_DEBUG
                );
            }
        }
    }

    /**
     * Build out-tree graph from defined Processes and their relations.
     *
     * @return OutTree
     */
    public function buildTree()
    {
        if (!$this->tree) {
            $root = $this->graph->createVertex(0);

            // Create edges directed from the root node
            foreach ($this->processes as $processClassName => $processObject) {
                $vertex = $this->graph->getVertex($processClassName);

                if (!$processObject->delayMinutes) { // process doesn't depend on anything => link it to the root node
                    $root->createEdgeTo($vertex)->setWeight(0);
                } else { // link process to its dependency
                    // Throw error if dependency is to not existing vertex
                    if (!$this->graph->hasVertex($processObject->delayAfter)) {
                        throw new \InvalidArgumentException(
                            sprintf(
                                'Testcase "%s" has @delayAfter dependency on "%s", but this testcase was not defined.',
                                $processClassName,
                                $processObject->delayAfter
                            )
                        );
                    }

                    $this->graph->getVertex($processObject->delayAfter)
                        ->createEdgeTo($vertex)
                        ->setWeight($processObject->delayMinutes);
                }
            }
        }

        $this->tree = new OutTree($this->graph);

        if (!$this->tree->isTree()) {
            throw new \InvalidArgumentException(
                sprintf('Cannot build tree graph from tests dependencies. Probably some cyclic dependency is present.')
            );
        }

        return $this->tree;
    }

    /**
     * Optimize order of processes using given strategy.
     *
     * @param OptimizeOrderInterface $optimizeStrategy
     */
    public function optimizeOrder(OptimizeOrderInterface $optimizeStrategy)
    {
        $optimizedOrder = $optimizeStrategy->optimize($this->buildTree());

        // Sort the $optimizedOrder array to have the same order as corresponding array of processes
        // (so that the array could be passed to array_multisort())
        $sortingArray = [];
        foreach ($this->processes as $processClassName => $processObject) {
            $sortingArray[$processClassName] = $optimizedOrder[$processClassName];
        }

        // Sort processes descending according to corresponding values in $sortingArray
        array_multisort($sortingArray, SORT_DESC, SORT_NUMERIC, $this->processes);
    }

    /**
     * Get count of processes status
     *
     * @return array
     */
    public function countStatuses()
    {
        $statusesCount = [];
        foreach (self::$processStatuses as $status) {
            $statusesCount[$status] = count($this->get($status));
        }

        return $statusesCount;
    }

    /**
     * Get result counts of done processes
     *
     * @return array
     */
    public function countResults()
    {
        $done = $this->get(self::PROCESS_STATUS_DONE);
        $doneClasses = [];
        $resultsCount = [
            self::PROCESS_RESULT_PASSED => 0,
            self::PROCESS_RESULT_FAILED => 0,
            self::PROCESS_RESULT_FATAL => 0,
        ];

        // Retrieve names of done processes and count their results
        foreach ($done as $className => $processObject) {
            $doneClasses[] = $className;
            $resultsCount[$processObject->result]++;
        }

        return $resultsCount;
    }

    /**
     * Resolve result of finished process of given class
     *
     * @param string $className
     * @return string
     */
    private function resolveResult($className)
    {
        switch ($this->processes[$className]->process->getExitCode()) {
            case \PHPUnit_TextUI_TestRunner::SUCCESS_EXIT: // all tests passed
                $result = self::PROCESS_RESULT_PASSED;
                // for passed process save just the status and result; end time was saved by TestStatusListener
                break;
            case 15: // Process killed because of timeout, or
            case 9: // Process terminated because of timeout
                $result = self::PROCESS_RESULT_FATAL;
                break;
            case 255: // PHP fatal error
                $result = self::PROCESS_RESULT_FATAL;
                break;
            case \PHPUnit_TextUI_TestRunner::EXCEPTION_EXIT: // exception thrown from phpunit
            case \PHPUnit_TextUI_TestRunner::FAILURE_EXIT: // some test failed
            default:
                $result = self::PROCESS_RESULT_FAILED;
                break;
        }

        return $result;
    }
}
