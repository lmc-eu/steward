<?php declare(strict_types=1);

namespace Lmc\Steward\Listener;

use Facebook\WebDriver\Exception\SessionNotCreatedException;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\CapabilitiesResolver;
use Lmc\Steward\Test\AbstractTestCase;
use Lmc\Steward\Utils\Annotations\ClassAnnotations;
use Lmc\Steward\WebDriver\NullWebDriver;
use Lmc\Steward\WebDriver\RemoteWebDriver;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\WarningTestCase;
use PHPUnit\Runner\BaseTestRunner;

/**
 * Listener for initialization and destruction of WebDriver before and after each test.
 *
 * Note: This is done as a listener rather then in setUp() and tearDown(), as a workaround
 * for the sequence in which PHPUnit executes tearDown() of tests and addFailure() on listeners.
 * If taking screenshot using addFailure(), tearDown() would have already been called and the
 * browser would be closed.
 */
class WebDriverListener implements TestListener
{
    use TestListenerDefaultImplementation;

    protected const NO_BROWSER_ANNOTATION = 'noBrowser';

    /** @var ConfigProvider */
    protected $config;
    /** @var CapabilitiesResolver */
    protected $capabilitiesResolver;

    public function __construct()
    {
        $this->config = ConfigProvider::getInstance();
    }

    protected function getCapabilitiesResolver(): CapabilitiesResolver
    {
        if ($this->capabilitiesResolver === null) {
            $this->capabilitiesResolver = new CapabilitiesResolver($this->config);
        }

        return $this->capabilitiesResolver;
    }

    public function startTest(Test $test): void
    {
        if ($test instanceof WarningTestCase) {
            return;
        }

        if (!$test instanceof AbstractTestCase) {
            throw new \InvalidArgumentException('Test case must be descendant of Lmc\Steward\Test\AbstractTestCase');
        }

        if ($test->getStatus() === BaseTestRunner::STATUS_SKIPPED) {
            return;
        }

        // Initialize NullWebDriver if self::NO_BROWSER_ANNOTATION is used on testcase class or test method
        $testCaseAnnotations = ClassAnnotations::getAnnotationsForInstance($test);
        $testAnnotations = ClassAnnotations::getAnnotationsForMethodOfInstance($test, $test->getName(false));

        if (isset($testCaseAnnotations[self::NO_BROWSER_ANNOTATION])
            || isset($testAnnotations[self::NO_BROWSER_ANNOTATION])
        ) {
            $test->wd = new NullWebDriver();
            $test->log(
                'Initializing Null WebDriver (@%s annotation used %s)',
                self::NO_BROWSER_ANNOTATION,
                isset($testCaseAnnotations[self::NO_BROWSER_ANNOTATION]) ? 'on class' : 'on method'
            );

            return;
        }

        // Initialize real WebDriver otherwise
        $test->log('Initializing "%s" WebDriver', $this->config->browserName);

        $desiredCapabilities = $this->getCapabilitiesResolver()->resolveDesiredCapabilities($test);

        $this->createWebDriver(
            $test,
            $this->config->serverUrl,
            $desiredCapabilities,
            $connectTimeoutMs = 2 * 60 * 1000,
            // How long could request to Selenium take (eg. how long could we wait in hub's queue to available node)
            $requestTimeoutMs = 60 * 60 * 1000 // 1 hour (same as timeout for the whole process)
        );
    }

    public function endTest(Test $test, float $time): void
    {
        if ($test instanceof WarningTestCase) {
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
                'Destroying "%s" WebDriver for session "%s"',
                ConfigProvider::getInstance()->browserName,
                $test->wd->getSessionID()
            );

            ob_start(); // Capture any output from commands bellow to make them appended to output of the test.

            try {
                $test->wd->close();
                $test->wd->quit();
            } catch (WebDriverException $e) {
                // Geckodriver always throw error when closing the session. We ignore it to not pollute the output.
                // https://github.com/mozilla/geckodriver/issues/732, https://bugzilla.mozilla.org/show_bug.cgi?id=1403510
                if (ConfigProvider::getInstance()->browserName !== WebDriverBrowserType::FIREFOX
                    || $e->getMessage() !== 'invalid session id') {
                    $test->warn('Error closing the session, browser may died.');
                }
            } finally {
                $output = ob_get_clean();
                $test->appendFormattedTestLog($output);
            }
        }
    }

    /**
     * Subroutine to encapsulate creation of real WebDriver.
     * The WebDriver instance is stored to $test->wd when created.
     */
    protected function createWebDriver(
        AbstractTestCase $test,
        string $remoteServerUrl,
        DesiredCapabilities $desiredCapabilities,
        int $connectTimeoutMs,
        int $requestTimeoutMs
    ): void {
        try {
            $test->wd = RemoteWebDriver::create(
                $remoteServerUrl,
                $desiredCapabilities,
                $connectTimeoutMs,
                $requestTimeoutMs,
                null,
                null
            );

            return;
        } catch (SessionNotCreatedException $e) {
            $test->warn('Unable to initialize new WebDriver session.');
            throw $e;
        }
    }
}
