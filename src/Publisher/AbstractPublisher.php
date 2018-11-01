<?php declare(strict_types=1);

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
    public const TEST_STATUS_STARTED = 'started';
    /** Test was finished */
    public const TEST_STATUS_DONE = 'done';

    /** Test passed */
    public const TEST_RESULT_PASSED = 'passed';
    /** Test failed (eg. some assertion does not match) */
    public const TEST_RESULT_FAILED = 'failed';
    /** Test was broken (eg. Exception was thrown, PHPUnit returns WarningTestCase etc.) */
    public const TEST_RESULT_BROKEN = 'broken';
    /** Test was skipped using markTestSkipped() */
    public const TEST_RESULT_SKIPPED = 'skipped';
    /** Test was skipped using markTestIncomplete() */
    public const TEST_RESULT_INCOMPLETE = 'incomplete';

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
        // @todo Remove after https://github.com/sebastianbergmann/phpunit/issues/3379 is fixed
        BaseTestRunner::STATUS_UNKNOWN => self::TEST_RESULT_SKIPPED,
    ];

    /**
     * Publish testcase result
     *
     * @param string $status One of ProcessSet::$processStatuses
     * @param string $result One of ProcessSet::$processResults
     */
    abstract public function publishResults(
        string $testCaseName,
        string $status,
        string $result = null,
        \DateTimeInterface $testCaseStartDate = null,
        \DateTimeInterface $testCaseEndDate = null
    ): void;

    /**
     * Publish results of one single test
     *
     * @param string $status One of self::$testStatuses
     * @param string $result One of self::$testResults
     */
    abstract public function publishResult(
        string $testCaseName,
        string $testName,
        Test $testInstance,
        string $status,
        string $result = null,
        string $message = null
    ): void;

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
