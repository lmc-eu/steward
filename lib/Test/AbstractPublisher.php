<?php

namespace Lmc\Steward\Test;

/**
 * Abstract test results publisher.
 */
abstract class AbstractPublisher
{
    /** @var array */
    public static $testResultsMap = [
        -1 => 'running',
        \PHPUnit_Runner_BaseTestRunner::STATUS_PASSED => 'passed',
        \PHPUnit_Runner_BaseTestRunner::STATUS_SKIPPED => 'skipped',
        \PHPUnit_Runner_BaseTestRunner::STATUS_INCOMPLETE => 'incomplete',
        \PHPUnit_Runner_BaseTestRunner::STATUS_FAILURE => 'failed',
        \PHPUnit_Runner_BaseTestRunner::STATUS_ERROR => 'broken',
    ];

    /** @var string */
    protected $environment;

    /** @var string */
    protected $jobName;

    /** @var int */
    protected $buildNumber;

    /** @var bool */
    protected $debug = false;

    /**
     * @param string $environment
     * @param string $jobName
     * @param int $buildNumber
     */
    abstract public function __construct($environment, $jobName, $buildNumber);

    /**
     * Publish testcase result
     *
     * @param string $testCaseName
     * @param string $status {prepared, queued, done}
     * @param string $result {passed, failed, fatal}
     * @param \DateTimeInterface $startDate Testcase start datetime
     * @param \DateTimeInterface $endDate Testcase end datetime
     */
    abstract public function publishResults(
        $testCaseName,
        $status,
        $result = null,
        \DateTimeInterface $startDate = null,
        \DateTimeInterface $endDate = null
    );

    /**
     * Publish results of one single test
     *
     * @param string $testCaseName
     * @param string $testName
     * @param string $status {started, done}
     * @param string $result {passed, failed, broken, skipped, incomplete}
     * @param string $message
     */
    abstract public function publishResult($testCaseName, $testName, $status, $result = null, $message = null);

    /**
     * Is debug mode enabled?
     * @return bool
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * Enable debug mode
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }
}
