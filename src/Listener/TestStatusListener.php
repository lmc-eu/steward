<?php

namespace Lmc\Steward\Listener;

use Lmc\Steward\Publisher\AbstractPublisher;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Process\ProcessWrapper;

/**
 * Listener to log status of test case and at the end of suite publish them using registered publishers.
 */
class TestStatusListener extends \PHPUnit_Framework_BaseTestListener
{
    /** @var AbstractPublisher[] $publishers */
    protected $publishers = [];

    /** @var string $startDate */
    protected $startDate;

    /**
     * @param array $testPublishers Array of fully qualified names of AbstractPublisher classes
     */
    public function __construct(array $testPublishers)
    {
        $config = ConfigProvider::getInstance();

        // always register XmlPublisher
        $publishersToRegister[] = 'Lmc\\Steward\\Publisher\\XmlPublisher';

        // other publishers register only if $config->publishResults is true
        if ($config->publishResults) {
            $publishersToRegister = array_merge($publishersToRegister, $testPublishers);
        }

        foreach ($publishersToRegister as $publisherClass) {
            if (!class_exists($publisherClass)) {
                throw new \RuntimeException(
                    sprintf('Cannot add new test publisher, class "%s" not found', $publisherClass)
                );
            }

            $publisher = new $publisherClass($config->env, getenv('JOB_NAME'), (int) getenv('BUILD_NUMBER'));
            if (!$publisher instanceof AbstractPublisher) {
                throw new \RuntimeException(
                    sprintf(
                        'Cannot add new test publisher, class "%s" must be an instance of "AbstractPublisher"',
                        $publisherClass
                    )
                );
            }
            if ($config->debug) {
                printf('[%s]: Registering test results publisher "%s"' . "\n", date("Y-m-d H:i:s"), $publisherClass);
            }
            $this->publishers[] = $publisher;
        }
    }

    public function startTestSuite(\PHPUnit_Framework_TestSuite $suite)
    {
        $this->startDate = new \DateTimeImmutable();
    }

    public function startTest(\PHPUnit_Framework_Test $test)
    {
        if (!$test instanceof \PHPUnit_Framework_TestCase || $test instanceof \PHPUnit_Framework_Warning) {
            return;
        }
        // publish test status to all publishers
        foreach ($this->publishers as $publisher) {
            try {
                $publisher->publishResult(
                    get_class($test),
                    $test->getName(),
                    $status = AbstractPublisher::TEST_STATUS_STARTED
                );
            } catch (\Exception $e) {
                printf(
                    '[%s] [WARN]: Error publishing test started status to "%s" ("%s")' . "\n",
                    date("Y-m-d H:i:s"),
                    get_class($publisher),
                    $e->getMessage()
                );
            }
        }
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        if (!$test instanceof \PHPUnit_Framework_TestCase || $test instanceof \PHPUnit_Framework_Warning) {
            return;
        }
        // publish test status to all publishers
        foreach ($this->publishers as $publisher) {
            try {
                $publisher->publishResult(
                    get_class($test),
                    $test->getName(),
                    $status = AbstractPublisher::TEST_STATUS_DONE,
                    $result = AbstractPublisher::$testResultsMap[$test->getStatus()],
                    $test->getStatusMessage()
                );
            } catch (\Exception $e) {
                printf(
                    '[%s] [WARN]: Error publishing test done status to "%s" ("%s")' . "\n",
                    date("Y-m-d H:i:s"),
                    get_class($publisher),
                    $e->getMessage()
                );
            }
        }
    }

    public function endTestSuite(\PHPUnit_Framework_TestSuite $suite)
    {
        if ($suite instanceof \PHPUnit_Framework_TestSuite_DataProvider) {
            return;
        }

        // publish to all publishers
        foreach ($this->publishers as $publisher) {
            try {
                $publisher->publishResults(
                    $suite->getName(),
                    $status = ProcessWrapper::PROCESS_STATUS_DONE,
                    $result = null, // do not override, the value is set by ProcessSet::setStatus()
                    $this->startDate,
                    new \DateTimeImmutable()
                );
            } catch (\Exception $e) {
                printf(
                    '[%s] [WARN]: Error publishing process done status to "%s" ("%s")' . "\n",
                    date("Y-m-d H:i:s"),
                    get_class($publisher),
                    $e->getMessage()
                );
            }
        }
    }
}
