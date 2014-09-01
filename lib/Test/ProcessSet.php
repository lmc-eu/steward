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

    /** Process prepared to be run */
    const PROCESS_STATUS_PREPARED = 'prepared';
    /** Process in queue  - waiting to be prepared */
    const PROCESS_STATUS_QUEUED = 'queued';
    /** Finished process */
    const PROCESS_STATUS_FINISHED = 'finished';

    public static $statuses = [
        self::PROCESS_STATUS_PREPARED,
        self::PROCESS_STATUS_QUEUED,
        self::PROCESS_STATUS_FINISHED,
    ];

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
                    'Test "%s" should run after "%s", but not delay was defined',
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
            'status' => 'queued',
            'process' => $process,
            'delayAfter' => $delayAfter,
            'delayMinutes' => $delayMinutes,
            'finishedTime' => null,
        ];
    }

    /**
     * Get array of processes in the set having given status
     *
     * @param string $status
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
     * @param type $className
     */
    public function remove($className)
    {
        unset($this->processes[$className]);
    }

    /**
     * Set status of given process
     * @param $className
     * @param $status
     * @throws \InvalidArgumentException
     */
    public function setStatus($className, $status)
    {
        if (!in_array($status, self::$statuses)) {
            throw new \InvalidArgumentException(
                sprintf('Process status must be one of "%s", but "%s" given', join(', ', self::$statuses), $status)
            );
        }
        $this->processes[$className]->status = $status;
    }

    /**
     * Check dependencies of all queued processes
     * @return array
     */
    public function checkDependencies()
    {
        $invalidDependencies = [];

        // Ensure dependencies links to existing classes
        $queuedProcesses = $this->get('queued');
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
