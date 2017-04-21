<?php

namespace Lmc\Steward\Listener;

use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Process\ProcessWrapper;
use Lmc\Steward\Publisher\AbstractPublisher;
use Lmc\Steward\Publisher\SauceLabsPublisher;
use Lmc\Steward\Publisher\TestingBotPublisher;
use Lmc\Steward\Publisher\XmlPublisher;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use PHPUnit\Framework\BaseTestListener;

/**
 * Listener to log status of test case and at the end of suite publish them using registered publishers.
 */
class TestStatusListener extends BaseTestListener
{
    /** @var AbstractPublisher[] $publishers */
    protected $publishers = [];

    /** @var \DateTimeInterface $startDate */
    protected $startDate;

    /**
     * @param string[] $customTestPublishers Array of fully qualified names of AbstractPublisher classes
     * @param SeleniumServerAdapter $seleniumServerAdapter Inject SeleniumServerAdapter. Used only for tests.
     */
    public function __construct(array $customTestPublishers, SeleniumServerAdapter $seleniumServerAdapter = null)
    {
        $config = ConfigProvider::getInstance();
        if (is_null($seleniumServerAdapter)) {
            $seleniumServerAdapter = new SeleniumServerAdapter($config->serverUrl);
        }

        // always register XmlPublisher
        $publishersToRegister = [XmlPublisher::class];

        // If current server is SauceLabs/TestingBot, autoregister its publisher
        if ($seleniumServerAdapter->getCloudService() == SeleniumServerAdapter::CLOUD_SERVICE_SAUCELABS) {
            $publishersToRegister[] = SauceLabsPublisher::class;
        } elseif ($seleniumServerAdapter->getCloudService() == SeleniumServerAdapter::CLOUD_SERVICE_TESTINGBOT) {
            $publishersToRegister[] = TestingBotPublisher::class;
        }

        // register custom publishers
        $publishersToRegister = array_merge($publishersToRegister, $customTestPublishers);

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
                printf('[%s]: Registering test results publisher "%s"' . "\n", date('Y-m-d H:i:s'), $publisherClass);
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
        if (!$test instanceof \PHPUnit_Framework_TestCase || $test instanceof \PHPUnit_Framework_WarningTestCase) {
            return;
        }

        // publish test status to all publishers
        foreach ($this->publishers as $publisher) {
            try {
                $publisher->publishResult(
                    get_class($test),
                    $test->getName(),
                    $test,
                    $status = AbstractPublisher::TEST_STATUS_STARTED
                );
            } catch (\Exception $e) {
                printf(
                    '[%s] [WARN]: Error publishing test started status to "%s" ("%s")' . "\n",
                    date('Y-m-d H:i:s'),
                    get_class($publisher),
                    $e->getMessage()
                );
            }
        }
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        if (!$test instanceof \PHPUnit_Framework_TestCase || $test instanceof \PHPUnit_Framework_WarningTestCase) {
            return;
        }

        // publish test status to all publishers
        foreach ($this->publishers as $publisher) {
            try {
                $publisher->publishResult(
                    get_class($test),
                    $test->getName(),
                    $test,
                    $status = AbstractPublisher::TEST_STATUS_DONE,
                    $result = AbstractPublisher::$testResultsMap[$test->getStatus()],
                    $test->getStatusMessage()
                );
            } catch (\Exception $e) {
                printf(
                    '[%s] [WARN]: Error publishing test done status to "%s" ("%s")' . "\n",
                    date('Y-m-d H:i:s'),
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
                    date('Y-m-d H:i:s'),
                    get_class($publisher),
                    $e->getMessage()
                );
            }
        }
    }
}
