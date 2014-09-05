<?php

namespace Lmc\Steward\Test;

/**
 * Abstract test results publisher.
 */
abstract class AbstractPublisher
{
    /** Test started and is currently executed by Selenium */
    const TEST_STATUS_STARTED = 'started';
    /** Test was finished */
    const TEST_STATUS_DONE = 'done';

    /** Test passed */
    const TEST_RESULT_PASSED = 'passed';
    /** Test failed (eg. some assertion does not match) */
    const TEST_RESULT_FAILED = 'failed';
    /** Test was broken (ie. Exception was thrown) */
    const TEST_RESULT_BROKEN = 'broken';
    /** Test was skipped using markTestSkipped() */
    const TEST_RESULT_SKIPPED = 'skipped';
    /** Test was skipped using markTestIncomplete() */
    const TEST_RESULT_INCOMPLETE = 'incomplete';

    /** @var array List of possible test statuses */
    public static $testStatuses = [
        self::TEST_STATUS_STARTED,
        self::TEST_STATUS_DONE,
    ];

    /** @var array List of possible test results */
    public static $testResults = [
        self::TEST_RESULT_PASSED,
        self::TEST_RESULT_FAILED,
        self::TEST_RESULT_BROKEN,
        self::TEST_RESULT_SKIPPED,
        self::TEST_RESULT_INCOMPLETE,
    ];

    /** @var array Map of PHPUnit test results constants to our tests results */
    public static $testResultsMap = [
        \PHPUnit_Runner_BaseTestRunner::STATUS_PASSED => self::TEST_RESULT_PASSED,
        \PHPUnit_Runner_BaseTestRunner::STATUS_SKIPPED => self::TEST_RESULT_SKIPPED,
        \PHPUnit_Runner_BaseTestRunner::STATUS_INCOMPLETE => self::TEST_RESULT_INCOMPLETE,
        \PHPUnit_Runner_BaseTestRunner::STATUS_FAILURE => self::TEST_RESULT_FAILED,
        \PHPUnit_Runner_BaseTestRunner::STATUS_ERROR => self::TEST_RESULT_BROKEN,
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
     * @param string $status One of ProcessSet::$processStatuses
     * @param string $result One of ProcessSet::$processResults
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
     * @param string $status One of self::$testStatuses
     * @param string $result One of self::$testResults
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
