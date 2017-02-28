<?php

namespace Lmc\Steward\Process;

use Assert\Assert;
use Assert\Assertion;
use Lmc\Steward\Publisher\AbstractPublisher;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Wrapper for PHPUnit processes adding some metadata and custom logic
 */
class ProcessWrapper
{
    /** Process prepared to be run */
    const PROCESS_STATUS_PREPARED = 'prepared';
    /** Finished process */
    const PROCESS_STATUS_DONE = 'done';
    /** Process in queue  - waiting to be prepared */
    const PROCESS_STATUS_QUEUED = 'queued';

    /** Process failed - some tests have failed or are broken */
    const PROCESS_RESULT_FAILED = 'failed';
    /** Process fatally failed (PHP fatal error occurred - eg. no WebDriver available) */
    const PROCESS_RESULT_FATAL = 'fatal';
    /** Process passed successful (with all its tests passing) */
    const PROCESS_RESULT_PASSED = 'passed';

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

    /** @var AbstractPublisher */
    private $publisher;
    /** @var Process */
    private $process;
    /** @var string */
    private $className;
    /** @var string */
    private $delayAfter;
    /** @var float */
    private $delayMinutes;
    /** @var string */
    private $status;
    /** @var string */
    private $result;
    /** @var int */
    private $finishedTime;

    /**
     * @param Process $process Instance of PHPUnit process
     * @param string $className Tested class fully qualified name
     * @param AbstractPublisher $publisher
     */
    public function __construct(Process $process, $className, AbstractPublisher $publisher = null)
    {
        $this->process = $process;
        $this->className = $className;
        $this->publisher = $publisher;
        $this->setStatus(self::PROCESS_STATUS_QUEUED);
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param string $afterClass Fully qualified class name after which this test should be run.
     * @param float $minutes Delay execution for that much $minutes after $afterClass test.
     */
    public function setDelay($afterClass, $minutes)
    {
        Assertion::notNull(
            $minutes,
            sprintf('Testcase "%s" should run after "%s", but no delay was defined', $this->getClassName(), $afterClass)
        );

        $assertionError = sprintf(
            'Delay defined in testcase "%s" must be greater than or equal 0, but "%s" was given',
            $this->getClassName(),
            $minutes
        );
        Assert::that($minutes)->numeric($assertionError)->greaterOrEqualThan(0, $assertionError);

        $this->delayAfter = $afterClass;
        $this->delayMinutes = (float) $minutes;
    }

    /**
     * @return bool
     */
    public function isDelayed()
    {
        if (!empty($this->delayAfter)) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getDelayAfter()
    {
        return $this->delayAfter;
    }

    /**
     * @return float
     */
    public function getDelayMinutes()
    {
        return $this->delayMinutes;
    }

    /**
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        Assertion::choice($status, self::$processStatuses);

        $this->status = $status;

        if ($status == self::PROCESS_STATUS_DONE) {
            $this->result = $this->resolveResult();
            $this->finishedTime = time();
        }

        if ($this->publisher) {
            $this->publisher->publishResults($this->getClassName(), $status, $this->result);
        }
    }

    /**
     * @return string
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return int
     */
    public function getFinishedTime()
    {
        return $this->finishedTime;
    }

    /**
     * Check if process is not running longer then specified timeout, return error message if so.
     * @return null|string Error message if process timeout exceeded
     */
    public function checkProcessTimeout()
    {
        try {
            $this->getProcess()->checkTimeout();
        } catch (ProcessTimedOutException $e) {
            $this->setStatus(self::PROCESS_STATUS_DONE);

            return sprintf(
                'Process for class "%s" exceeded the timeout of %d seconds and was killed.',
                $this->getClassName(),
                $e->getExceededTimeout()
            );
        }

        return '';
    }

    /**
     * Resolve result of finished process
     *
     * @return string
     */
    private function resolveResult()
    {
        $exitCode = $this->getProcess()->getExitCode();

        // If the process was not even started, mark its result as failed
        if ($exitCode === null) {
            return self::PROCESS_RESULT_FAILED;
        }

        switch ($exitCode) {
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
