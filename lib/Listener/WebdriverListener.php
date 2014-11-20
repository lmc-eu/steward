<?php

namespace Lmc\Steward\Listener;

use Lmc\Steward\Test\AbstractTestCase;
use Lmc\Steward\WebDriver\NullWebDriver;
use Lmc\Steward\WebDriver\RemoteWebDriver;
use Nette\Reflection\AnnotationsParser;

/**
 * Listener for initialization and destruction of WebDriver before and after each test.
 *
 * Note: This is done as a listener rather then in setUp() and tearDown(), as a workaround
 * for the sequence in which PHPUnit executes tearDown() of tests and addFailure() on listeners.
 * If taking screenshot using addFailure(), tearDown() would have already been called and the
 * browser would be closed.
 */
class WebdriverListener extends \PHPUnit_Framework_BaseTestListener
{
    const NO_BROWSER_ANNOTATION = 'noBrowser';

    public function startTest(\PHPUnit_Framework_Test $test)
    {
        if (!$test instanceof AbstractTestCase) {
            throw new \InvalidArgumentException('Test case must be descendant of Lmc\Steward\Test\AbstractTestCase');
        }

        // Initialize NullWebdriver if self::NO_BROWSER_ANNOTATION is used
        $testCaseAnnotations = AnnotationsParser::getAll(new \ReflectionClass($test));
        if ($testCaseAnnotations[self::NO_BROWSER_ANNOTATION]) {
            $test->wd = new NullWebDriver();
            $test->log(
                'Initializing Null webdriver for "%s::%s" (@%s annotation used)',
                get_class($test),
                $test->getName(),
                self::NO_BROWSER_ANNOTATION
            );

            return;
        }

        // Initialize real WebDriver otherwise
        $test->log('Initializing "%s" webdriver for "%s::%s"', BROWSER_NAME, get_class($test), $test->getName());

        $capabilities = new \DesiredCapabilities(
            [
                \WebDriverCapabilityType::BROWSER_NAME => BROWSER_NAME,
                \WebDriverCapabilityType::PLATFORM => \WebDriverPlatform::ANY,
            ]
        );

        $capabilities = $this->setupCustomCapabilities($capabilities, BROWSER_NAME);

        $test->wd = RemoteWebDriver::create(SERVER_URL .  '/wd/hub', $capabilities, $timeoutInMs = 2*60*1000);
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        if (!$test instanceof AbstractTestCase) {
            throw new \InvalidArgumentException('Test case must be descendant of Lmc\Steward\Test\AbstractTestCase');
        }

        if ($test->wd instanceof \RemoteWebDriver) {
            $test->log(
                'Destroying "%s" webdriver for "%s::%s" (session %s)',
                BROWSER_NAME,
                get_class($test),
                $test->getName(),
                $test->wd->getSessionID()
            );

            // Workaround for PhantomJS 1.x - see https://github.com/detro/ghostdriver/issues/343
            // Should be removed with PhantomJS 2
            $test->wd->execute('deleteAllCookies');

            $test->wd->close();
            $test->wd->quit();
        }
    }

    /**
     * Setup browser-specific custom capabilities.
     * @param \DesiredCapabilities $capabilities
     * @param string $browser Browser name
     * @return \DesiredCapabilities
     */
    protected function setupCustomCapabilities(\DesiredCapabilities $capabilities, $browser)
    {
        switch ($browser) {
            case \WebDriverBrowserType::FIREFOX:
                $capabilities = $this->setupFirefoxCapabilities($capabilities);
                break;
            case \WebDriverBrowserType::CHROME:
                $capabilities = $this->setupChromeCapabilities($capabilities);
                break;
            case \WebDriverBrowserType::IE:
                $capabilities = $this->setupInternetExplorerCapabilities($capabilities);
                break;
            case \WebDriverBrowserType::SAFARI:
                $capabilities = $this->setupSafariCapabilities($capabilities);
                break;
            case \WebDriverBrowserType::PHANTOMJS:
                $capabilities = $this->setupPhantomjsCapabilities($capabilities);
                break;
        }

        return $capabilities;
    }

    /**
     * Set up Firefox-specific capabilities
     * @param \DesiredCapabilities $capabilities
     * @return \DesiredCapabilities
     */
    protected function setupFirefoxCapabilities(\DesiredCapabilities $capabilities)
    {
        // Firefox does not (as a intended feature) trigger "change" and "focus" events in javascript if not in active
        // (focused) window. This would be a problem for concurrent testing - solution is to use focusmanager.testmode.
        // See https://code.google.com/p/selenium/issues/detail?id=157
        $profile = new \FirefoxProfile(); // see https://github.com/facebook/php-webdriver/wiki/FirefoxProfile
        $profile->setPreference(
            'focusmanager.testmode',
            true
        );

        $capabilities->setCapability(\FirefoxDriver::PROFILE, $profile);

        return $capabilities;
    }

    /**
     * Set up Chrome/Chromium-specific capabilities
     * @param \DesiredCapabilities $capabilities
     * @return \DesiredCapabilities
     */
    protected function setupChromeCapabilities(\DesiredCapabilities $capabilities)
    {
        return $capabilities;
    }

    /**
     * Set up Internet Explorer-specific capabilities
     * @param \DesiredCapabilities $capabilities
     * @return \DesiredCapabilities
     */
    protected function setupInternetExplorerCapabilities(\DesiredCapabilities $capabilities)
    {
        // Clears cache, cookies, history, and saved form data of MSIE.
        $capabilities->setCapability('ie.ensureCleanSession', true);

        return $capabilities;
    }

    /**
     * Set up Safari-specific capabilities
     * @param \DesiredCapabilities $capabilities
     * @return \DesiredCapabilities
     */
    protected function setupSafariCapabilities(\DesiredCapabilities $capabilities)
    {
        return $capabilities;
    }

    /**
     * Set up PhantomJS-specific capabilities
     * @param \DesiredCapabilities $capabilities
     * @return \DesiredCapabilities
     */
    protected function setupPhantomjsCapabilities(\DesiredCapabilities $capabilities)
    {
        return $capabilities;
    }
}
