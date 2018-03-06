<?php

namespace Lmc\Steward\Publisher;

use Assert\Assertion;
use PHPUnit\Framework\Test;
use PHPUnit\Runner\BaseTestRunner;

/**
 * Abstract test results publisher could be extended and used for reporting test results into some custom system.
 * Any ancestor class must be registered to TestStatusListener (using phpunit.xml).
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
    /** Test was broken (eg. Exception was thrown, PHPUnit returns WarningTestCase etc.) */
    const TEST_RESULT_BROKEN = 'broken';
    /** Test was skipped using markTestSkipped() */
    const TEST_RESULT_SKIPPED = 'skipped';
    /** Test was skipped using markTestIncomplete() */
    const TEST_RESULT_INCOMPLETE = 'incomplete';

    /** @var array List of possible test statuses */
    public const TEST_STATUSES = [
        self::TEST_STATUS_STARTED,
        self::TEST_STATUS_DONE,
    ];

    /** @var array List of possible test results */
    public const TEST_RESULTS = [
        self::TEST_RESULT_PASSED,
        self::TEST_RESULT_FAILED,
        self::TEST_RESULT_BROKEN,
        self::TEST_RESULT_SKIPPED,
        self::TEST_RESULT_INCOMPLETE,
    ];
    /** @var array Map of PHPUnit test results constants to our tests results */
    private const TEST_RESULTS_MAP = [
        BaseTestRunner::STATUS_PASSED => self::TEST_RESULT_PASSED,
        BaseTestRunner::STATUS_SKIPPED => self::TEST_RESULT_SKIPPED,
        BaseTestRunner::STATUS_INCOMPLETE => self::TEST_RESULT_INCOMPLETE,
        BaseTestRunner::STATUS_FAILURE => self::TEST_RESULT_FAILED,
        BaseTestRunner::STATUS_ERROR => self::TEST_RESULT_BROKEN,
        BaseTestRunner::STATUS_RISKY => self::TEST_RESULT_BROKEN,
        BaseTestRunner::STATUS_WARNING => self::TEST_RESULT_BROKEN,
    ];

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
     * @param Test $testInstance
     * @param string $status One of self::$testStatuses
     * @param string $result One of self::$testResults
     * @param string $message
     */
    abstract public function publishResult(
        $testCaseName,
        $testName,
        Test $testInstance,
        $status,
        $result = null,
        $message = null
    );

    public static function getResultForPhpUnitTestStatus(int $phpUnitTestStatus): string
    {
        Assertion::keyExists(
            self::TEST_RESULTS_MAP,
            $phpUnitTestStatus,
            'PHPUnit test status "%s" is not known to Steward'
        );

        return self::TEST_RESULTS_MAP[$phpUnitTestStatus];
    }
}
