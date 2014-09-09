<?php

namespace Lmc\Steward\Test;

use Doctrine\Common\Proxy\Exception\InvalidArgumentException;
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
    /** Process fatally failed (PHP fatal error occurred - eg. no webdriver available) */
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
     * @param string $delayAfter OPTIONAL Other fully qualified class name after which this test should be run.
     * If is set, $delayMinutes must be > 0
     * @param int $delayMinutes OPTIONAL Delay execution for $delayMinutes after $delayAfter test
     */

    public function add(Process $process, $className, $delayAfter = '', $delayMinutes = 0)
    {
        $delayMinutes = abs((int) $delayMinutes);
        if (!empty($delayAfter) && $delayMinutes === 0) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Test "%s" should run after "%s", but no delay was defined',
                    $className,
                    $delayAfter
                )
            );
        }
        if ($delayMinutes !== 0 && empty($delayAfter)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Test "%s" has defined delay %d minutes, but does not have defined the task to run after',
                    $className,
                    $delayMinutes
                )
            );
        }

        $this->processes[$className] = (object) [
            'status' => self::PROCESS_STATUS_QUEUED,
            'process' => $process,
            'delayAfter' => $delayAfter,
            'delayMinutes' => $delayMinutes,
            'finishedTime' => null,
        ];

        $this->publisher->publishResults($className, self::PROCESS_STATUS_QUEUED, '');
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
     * Remove process from the set - no matter its status.
     *
     * @param string $className
     */
    public function remove($className)
    {
        unset($this->processes[$className]);
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

        $result = '';
        if ($status == self::PROCESS_STATUS_DONE) {
            switch ($this->processes[$className]->process->getExitCode()) {
                case \PHPUnit_TextUI_TestRunner::STATUS_PASSED: // all tests passed
                    $result = self::PROCESS_RESULT_PASSED;
                    // for passed process save just the status and result; end time was saved by TestStatusListener
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
        }
        $this->publisher->publishResults($className, $status, $result);
    }

    /**
     * Check dependencies of all queued processes and remove invalid ones from the set
     * @return array Array of invalid dependencies removed from the set
     */
    public function checkDependencies()
    {
        $invalidDependencies = [];

        // Ensure dependencies links to existing classes
        $queuedProcesses = $this->get(self::PROCESS_STATUS_QUEUED);
        foreach ($queuedProcesses as $className => $processObject) {
            if (!empty($processObject->delayAfter)
                && !array_key_exists($processObject->delayAfter, $queuedProcesses)
            ) {
                $invalidDependencies[] = $className;
                $this->remove($className);
            }
        }

        return $invalidDependencies;
    }
}
