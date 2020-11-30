<?php declare(strict_types=1);

namespace Lmc\Steward\Process;

use Assert\Assert;
use Assert\Assertion;
use Lmc\Steward\Publisher\AbstractPublisher;
use PHPUnit\TextUI\TestRunner;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Wrapper for PHPUnit processes adding some metadata and custom logic
 */
class ProcessWrapper
{
    /** Process prepared to be run */
    public const PROCESS_STATUS_PREPARED = 'prepared';
    /** Finished process */
    public const PROCESS_STATUS_DONE = 'done';
    /** Process in queue - waiting to be prepared */
    public const PROCESS_STATUS_QUEUED = 'queued';

    /** Process failed - some tests have failed or are broken */
    public const PROCESS_RESULT_FAILED = 'failed';
    /** Process fatally failed (PHP fatal error occurred - eg. no WebDriver available) */
    public const PROCESS_RESULT_FATAL = 'fatal';
    /** Process passed successful (with all its tests passing) */
    public const PROCESS_RESULT_PASSED = 'passed';

    /** @var array List of possible process statuses */
    public const PROCESS_STATUSES = [
        self::PROCESS_STATUS_PREPARED,
        self::PROCESS_STATUS_QUEUED,
        self::PROCESS_STATUS_DONE,
    ];
    /** @var array List of possible process results */
    public const PROCESS_RESULTS = [
        self::PROCESS_RESULT_PASSED,
        self::PROCESS_RESULT_FAILED,
        self::PROCESS_RESULT_FATAL,
    ];

    /** @var AbstractPublisher|null */
    private $publisher;
    /** @var Process */
    private $process;
    /** @var string */
    private $className;
    /** @var string|null */
    private $delayAfter;
    /** @var float|null */
    private $delayMinutes;
    /** @var string */
    private $status;
    /** @var string */
    private $result;
    /** @var int */
    private $finishedTime;

    public function __construct(Process $process, string $className, AbstractPublisher $publisher = null)
    {
        $this->process = $process;
        $this->className = $className;
        $this->publisher = $publisher;
        $this->setStatus(self::PROCESS_STATUS_QUEUED);
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @param string $afterClass Fully qualified class name after which this test should be run.
     * @param mixed $minutes Delay execution for that much $minutes after $afterClass test.
     */
    public function setDelay(string $afterClass, $minutes): void
    {
        Assertion::notNull(
            $minutes,
            sprintf(
                'Testcase "%s" should run after "%s", but no delay was defined using @delayMinutes',
                $this->getClassName(),
                $afterClass
            )
        );

        $assertionError = sprintf(
            'Delay defined in testcase "%s" using @delayMinutes must be greater than or equal 0, but "%s" was given',
            $this->getClassName(),
            $minutes
        );
        Assert::that($minutes)->numeric($assertionError)->greaterOrEqualThan(0, $assertionError);

        $this->delayAfter = $afterClass;
        $this->delayMinutes = (float) $minutes;
    }

    public function isDelayed(): bool
    {
        return $this->delayAfter !== null;
    }

    public function getDelayAfter(): ?string
    {
        return $this->delayAfter;
    }

    public function getDelayMinutes(): ?float
    {
        return $this->delayMinutes;
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        Assertion::choice($status, self::PROCESS_STATUSES);

        $this->status = $status;

        if ($status === self::PROCESS_STATUS_DONE) {
            $this->result = $this->resolveResult();
            $this->finishedTime = time();
        }

        if ($this->publisher) {
            $this->publisher->publishResults($this->getClassName(), $status, $this->result);
        }
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function getFinishedTime(): ?int
    {
        return $this->finishedTime;
    }

    /**
     * Check if process is not running longer then specified timeout, return error message if so.
     *
     * @return string Non-empty error message if process timeout exceeded
     */
    public function checkProcessTimeout(): ?string
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
     */
    private function resolveResult(): string
    {
        $exitCode = $this->getProcess()->getExitCode();

        // If the process was not even started, mark its result as failed
        if ($exitCode === null) {
            return self::PROCESS_RESULT_FAILED;
        }

        switch ($exitCode) {
            case TestRunner::SUCCESS_EXIT: // all tests passed
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
            case TestRunner::EXCEPTION_EXIT: // exception thrown from phpunit
            case TestRunner::FAILURE_EXIT: // some test failed
            default:
                $result = self::PROCESS_RESULT_FAILED;
                break;
        }

        return $result;
    }
}
