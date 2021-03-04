<?php declare(strict_types=1);

namespace Lmc\Steward\Listener;

use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Process\ProcessWrapper;
use Lmc\Steward\Publisher\AbstractPublisher;
use Lmc\Steward\Publisher\SauceLabsPublisher;
use Lmc\Steward\Publisher\TestingBotPublisher;
use Lmc\Steward\Publisher\XmlPublisher;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use PHPUnit\Framework\DataProviderTestSuite;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\WarningTestCase;

/**
 * Listener to log status of test case and at the end of suite publish them using registered publishers.
 */
class TestStatusListener implements TestListener
{
    use TestListenerDefaultImplementation;

    /** @var AbstractPublisher[] */
    protected $publishers = [];

    /** @var \DateTimeImmutable */
    protected $startDate;

    /**
     * @param string[] $customTestPublishers Array of fully qualified names of AbstractPublisher classes
     * @param SeleniumServerAdapter $seleniumServerAdapter Inject SeleniumServerAdapter. Used only for tests.
     */
    public function __construct(array $customTestPublishers, SeleniumServerAdapter $seleniumServerAdapter = null)
    {
        $config = ConfigProvider::getInstance();
        if ($seleniumServerAdapter === null) {
            $seleniumServerAdapter = new SeleniumServerAdapter($config->serverUrl);
        }

        // always register XmlPublisher
        $publishersToRegister = [XmlPublisher::class];

        // If current server is SauceLabs/TestingBot, autoregister its publisher
        if ($seleniumServerAdapter->getCloudService() === SeleniumServerAdapter::CLOUD_SERVICE_SAUCELABS) {
            $publishersToRegister[] = SauceLabsPublisher::class;
        } elseif ($seleniumServerAdapter->getCloudService() === SeleniumServerAdapter::CLOUD_SERVICE_TESTINGBOT) {
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
                printf('[%s] Registering test results publisher "%s"' . "\n", date('Y-m-d H:i:s'), $publisherClass);
            }
            $this->publishers[] = $publisher;
        }
    }

    public function startTestSuite(TestSuite $suite): void
    {
        $this->startDate = new \DateTimeImmutable();
    }

    public function startTest(Test $test): void
    {
        if (!$test instanceof TestCase || $test instanceof WarningTestCase) {
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
            } catch (\Throwable $e) {
                printf(
                    '[%s] [WARN] Error publishing test started status to "%s" ("%s")' . "\n",
                    date('Y-m-d H:i:s'),
                    get_class($publisher),
                    $e->getMessage()
                );
            }
        }
    }

    public function endTest(Test $test, float $time): void
    {
        if (!$test instanceof TestCase || $test instanceof WarningTestCase) {
            return;
        }

        // publish test status to all publishers
        foreach ($this->publishers as $publisher) {
            try {
                $publisher->publishResult(
                    get_class($test),
                    $test->getName(),
                    $test,
                    AbstractPublisher::TEST_STATUS_DONE,
                    AbstractPublisher::getResultForPhpUnitTestStatus($test->getStatus()),
                    $test->getStatusMessage()
                );
            } catch (\Throwable $e) {
                printf(
                    '[%s] [WARN] Error publishing test done status to "%s" ("%s")' . "\n",
                    date('Y-m-d H:i:s'),
                    get_class($publisher),
                    $e->getMessage()
                );
            }
        }
    }

    public function endTestSuite(TestSuite $suite): void
    {
        if ($suite instanceof DataProviderTestSuite) {
            return;
        }

        // publish to all publishers
        foreach ($this->publishers as $publisher) {
            try {
                $publisher->publishResults(
                    $suite->getName(),
                    ProcessWrapper::PROCESS_STATUS_DONE,
                    null, // do not override result, the value is set by ProcessSet::setStatus()
                    $this->startDate,
                    new \DateTimeImmutable()
                );
            } catch (\Throwable $e) {
                printf(
                    '[%s] [WARN] Error publishing process done status to "%s" ("%s")' . "\n",
                    date('Y-m-d H:i:s'),
                    get_class($publisher),
                    $e->getMessage()
                );
            }
        }
    }
}
