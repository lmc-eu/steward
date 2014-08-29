<?php

namespace Lmc\Steward\Test;

use PHPUnit_Framework_Test;

/**
 * Listener to log status of test case and at the end of suite publish them using registered publishers.
 */
class TestStatusListener extends \PHPUnit_Framework_BaseTestListener
{
    /** @var array $publishers */
    protected $publishers = [];

    /** @var string $startDate */
    protected $startDate;

    /**
     * @param array $testPublishers Array of fully qualified names of AbstractPublisher classes
     */
    public function __construct(array $testPublishers)
    {
        // register publishers if PUBLISH_RESULTS is true
        if (PUBLISH_RESULTS) {
            foreach ($testPublishers as $publisherClass) {
                if (!class_exists($publisherClass)) {
                    throw new \RuntimeException(
                        sprintf('Cannot add new test publisher, class "%s" not found', $publisherClass)
                    );
                }

                $publisher = new $publisherClass(ENV, getenv('JOB_NAME'), (int) getenv('BUILD_NUMBER'));
                if (!$publisher instanceof AbstractPublisher) {
                    throw new \RuntimeException(
                        sprintf(
                            'Cannot add new test publisher, class "%s" must be an instance of "AbstractPublisher"',
                            $publisherClass
                        )
                    );
                }
                if (DEBUG) {
                    echo sprintf('Registering test results publisher "%s"', $publisherClass) . "\n";
                    $publisher->setDebug(true);
                }
                $this->publishers[] = $publisher;
            }
        }
    }

    public function startTestSuite(\PHPUnit_Framework_TestSuite $suite)
    {
        $this->startDate = new \DateTimeImmutable();
    }

    public function startTest(PHPUnit_Framework_Test $test)
    {
        if ($test instanceof \PHPUnit_Framework_TestCase) {
            echo "--- startTest:" . $test->getName() . ' ' . get_class($test) . "\n";
        }
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        // publish test status to all publishers
        foreach ($this->publishers as $publisher) {
            $publisher->publishResult(
                get_class($test),
                $test->getName(),
                $test->getStatus(),
                $test->getStatusMessage()
            );
        }
    }

    public function endTestSuite(\PHPUnit_Framework_TestSuite $suite)
    {
        // publish to all publishers
        foreach ($this->publishers as $publisher) {
            $publisher->publishResults(
                $suite->getName(),
                'done',
                $this->startDate,
                new \DateTimeImmutable()
            );
        }
    }
}
