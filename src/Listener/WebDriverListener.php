<?php

namespace Lmc\Steward\Listener;

use Facebook\WebDriver\Exception\UnknownServerException;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\CapabilitiesResolver;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Lmc\Steward\Test\AbstractTestCase;
use Lmc\Steward\WebDriver\NullWebDriver;
use Lmc\Steward\WebDriver\RemoteWebDriver;
use Nette\Reflection\AnnotationsParser;
use PHPUnit\Framework\BaseTestListener;

/**
 * Listener for initialization and destruction of WebDriver before and after each test.
 *
 * Note: This is done as a listener rather then in setUp() and tearDown(), as a workaround
 * for the sequence in which PHPUnit executes tearDown() of tests and addFailure() on listeners.
 * If taking screenshot using addFailure(), tearDown() would have already been called and the
 * browser would be closed.
 */
class WebDriverListener extends BaseTestListener
{
    const NO_BROWSER_ANNOTATION = 'noBrowser';

    /** @var ConfigProvider */
    protected $config;
    /** @var CapabilitiesResolver */
    protected $capabilitiesResolver;

    public function __construct()
    {
        $this->config = ConfigProvider::getInstance();
    }

    /**
     * @return CapabilitiesResolver
     */
    protected function getCapabilitiesResolver()
    {
        if ($this->capabilitiesResolver === null) {
            $this->capabilitiesResolver = new CapabilitiesResolver($this->config);
        }

        return $this->capabilitiesResolver;
    }

    public function startTest(\PHPUnit_Framework_Test $test)
    {
        if ($test instanceof \PHPUnit_Framework_WarningTestCase) {
            return;
        }

        if (!$test instanceof AbstractTestCase) {
            throw new \InvalidArgumentException('Test case must be descendant of Lmc\Steward\Test\AbstractTestCase');
        }

        // Initialize NullWebDriver if self::NO_BROWSER_ANNOTATION is used on testcase class or test method
        $testCaseAnnotations = AnnotationsParser::getAll(new \ReflectionClass($test));
        $testAnnotations = AnnotationsParser::getAll(new \ReflectionMethod($test, $test->getName(false)));

        if (isset($testCaseAnnotations[self::NO_BROWSER_ANNOTATION])
            || isset($testAnnotations[self::NO_BROWSER_ANNOTATION])
        ) {
            $test->wd = new NullWebDriver();
            $test->log(
                'Initializing Null WebDriver for "%s::%s" (@%s annotation used %s)',
                get_class($test),
                $test->getName(),
                self::NO_BROWSER_ANNOTATION,
                isset($testCaseAnnotations[self::NO_BROWSER_ANNOTATION]) ? 'on class' : 'on method'
            );

            return;
        }

        // Initialize real WebDriver otherwise
        $test->log(
            'Initializing "%s" WebDriver for "%s::%s"',
            $this->config->browserName,
            get_class($test),
            $test->getName()
        );

        $desiredCapabilities = $this->getCapabilitiesResolver()->resolveDesiredCapabilities($test);
        $requiredCapabilities = $this->getCapabilitiesResolver()->resolveRequiredCapabilities($test);

        $this->createWebDriver(
            $test,
            $this->config->serverUrl . SeleniumServerAdapter::HUB_ENDPOINT,
            $desiredCapabilities,
            $requiredCapabilities,
            $connectTimeoutMs = 2 * 60 * 1000,
            // How long could request to Selenium take (eg. how long could we wait in hub's queue to available node)
            $requestTimeoutMs = 60 * 60 * 1000 // 1 hour (same as timeout for the whole process)
        );
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        if ($test instanceof \PHPUnit_Framework_WarningTestCase) {
            return;
        }

        if (!$test instanceof AbstractTestCase) {
            throw new \InvalidArgumentException('Test case must be descendant of Lmc\Steward\Test\AbstractTestCase');
        }

        if ($test->wd instanceof RemoteWebDriver) {
            // The endTest() method of WebDriverListener is called before endTest() of ResultPrinter, so any output
            // from this listener would overtake output from the tests itself, what would be confusing. Instead of this,
            // we append them to the output of the test itself, to print them in proper chronological order.
            $test->appendTestLog(
                'Destroying "%s" WebDriver for "%s::%s" (session %s)',
                ConfigProvider::getInstance()->browserName,
                get_class($test),
                $test->getName(),
                $test->wd->getSessionID()
            );

            ob_start(); // Capture any output from commands bellow to make them appended to output of the test.
            try {
                // Workaround for PhantomJS 1.x - see https://github.com/detro/ghostdriver/issues/343
                // Should be removed with PhantomJS 2
                if (ConfigProvider::getInstance()->browserName == WebDriverBrowserType::PHANTOMJS) {
                    $test->wd->execute('deleteAllCookies');
                }

                $test->wd->close();
                $test->wd->quit();
            } catch (WebDriverException $e) {
                $test->warn('Error closing the session, browser may died.');
            } finally {
                $output = ob_get_clean();
                $test->appendFormattedTestLog($output);
            }
        }
    }

    /**
     * Subroutine to encapsulate creation of real WebDriver. Handles some exceptions that may occur etc.
     * The WebDriver instance is stored to $test->wd when created.
     *
     * @param string AbstractTestCase $test
     * @param string $remoteServerUrl
     * @param DesiredCapabilities $desiredCapabilities
     * @param DesiredCapabilities $requiredCapabilities
     * @param int $connectTimeoutMs
     * @param int $requestTimeoutMs
     * @throws UnknownServerException
     */
    protected function createWebDriver(
        AbstractTestCase $test,
        $remoteServerUrl,
        DesiredCapabilities $desiredCapabilities,
        DesiredCapabilities $requiredCapabilities,
        $connectTimeoutMs,
        $requestTimeoutMs
    ) {
        $browserName = ConfigProvider::getInstance()->browserName;

        for ($startAttempts = 0; $startAttempts < 4; $startAttempts++) {
            try {
                $test->wd =
                    RemoteWebDriver::create(
                        $remoteServerUrl,
                        $desiredCapabilities,
                        $connectTimeoutMs,
                        $requestTimeoutMs,
                        null,
                        null,
                        $requiredCapabilities
                    );

                return;
            } catch (UnknownServerException $e) {
                if ($browserName == 'firefox'
                    && mb_strpos($e->getMessage(), 'Unable to bind to locking port') !== false
                ) {
                    // As a consequence of Selenium issue #5172 (cannot change locking port), Firefox may on CI server
                    // collide with other FF instance. As a workaround, we try to start it again after a short delay.
                    $test->warn(
                        'Firefox locking port is occupied; beginning attempt #%d to start it ("%s")',
                        $startAttempts + 2,
                        $e->getMessage()
                    );
                    sleep(1);
                    continue;
                } elseif (mb_strpos($e->getMessage(), 'Error forwarding the new session') !== false) {
                    $test->warn('Cannot execute test on the node. Maybe you started just the hub and not the node?');
                }
                throw $e;
            }
        }

        $test->warn('All %d attempts to instantiate Firefox WebDriver failed', $startAttempts + 1);
        throw $e;
    }
}
