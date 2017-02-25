<?php

namespace Lmc\Steward\Selenium;

use Facebook\WebDriver\Firefox\FirefoxDriver;
use Facebook\WebDriver\Firefox\FirefoxProfile;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverPlatform;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Test\AbstractTestCase;
use OndraM\CiDetector\CiDetector;

class CapabilitiesResolver implements CapabilitiesResolverInterface
{
    /** @var ConfigProvider */
    protected $config;
    /** @var CiDetector */
    protected $ciDetector;

    public function __construct(ConfigProvider $config)
    {
        $this->config = $config;

        $this->ciDetector = new CiDetector();
    }

    public function resolveDesiredCapabilities(AbstractTestCase $test)
    {
        $capabilities = new DesiredCapabilities(
            [
                WebDriverCapabilityType::BROWSER_NAME => $this->config->browserName,
                WebDriverCapabilityType::PLATFORM => WebDriverPlatform::ANY,
                'name' => get_class($test) . '::' . $test->getName(),
            ]
        );

        if (!empty($this->config->capability)) {
            $extraCapabilities = json_decode($this->config->capability);
            foreach ($extraCapabilities as $extraCapabilityName => $extraCapabilityValue) {
                $capabilities->setCapability($extraCapabilityName, $extraCapabilityValue);
            }
        }

        if ($this->ciDetector->isCiDetected()) {
            $ci = $this->ciDetector->detect();
            $capabilities->setCapability(
                'build',
                $this->config->env . '-' . $ci->getBuildNumber()
            );
            $capabilities->setCapability(
                'tags',
                [$this->config->env, $ci->getCiName(), get_class($test)]
            );
        }

        $capabilities = $this->setupCustomCapabilities($capabilities, $this->config->browserName);

        return $capabilities;
    }

    public function resolveRequiredCapabilities(AbstractTestCase $test)
    {
        return new DesiredCapabilities();
    }

    /**
     * @internal
     * @param CiDetector $ciDetector
     */
    public function setCiDetector(CiDetector $ciDetector)
    {
        $this->ciDetector = $ciDetector;
    }

    /**
     * Setup browser-specific custom capabilities.
     * @param DesiredCapabilities $capabilities
     * @param string $browser Browser name
     * @return DesiredCapabilities
     */
    protected function setupCustomCapabilities(DesiredCapabilities $capabilities, $browser)
    {
        switch ($browser) {
            case WebDriverBrowserType::FIREFOX:
                $capabilities = $this->setupFirefoxCapabilities($capabilities);
                break;
            case WebDriverBrowserType::CHROME:
                $capabilities = $this->setupChromeCapabilities($capabilities);
                break;
            case WebDriverBrowserType::MICROSOFT_EDGE:
                $capabilities = $this->setupMicrosoftEdgeCapabilities($capabilities);
                break;
            case WebDriverBrowserType::IE:
                $capabilities = $this->setupInternetExplorerCapabilities($capabilities);
                break;
            case WebDriverBrowserType::SAFARI:
                $capabilities = $this->setupSafariCapabilities($capabilities);
                break;
            case WebDriverBrowserType::PHANTOMJS:
                $capabilities = $this->setupPhantomjsCapabilities($capabilities);
                break;
        }

        return $capabilities;
    }

    /**
     * Set up Firefox-specific capabilities
     * @param DesiredCapabilities $capabilities
     * @return DesiredCapabilities
     */
    protected function setupFirefoxCapabilities(DesiredCapabilities $capabilities)
    {
        // Firefox does not (as a intended feature) trigger "change" and "focus" events in javascript if not in active
        // (focused) window. This would be a problem for concurrent testing - solution is to use focusmanager.testmode.
        // See https://code.google.com/p/selenium/issues/detail?id=157
        $profile = new FirefoxProfile(); // see https://github.com/facebook/php-webdriver/wiki/FirefoxProfile
        $profile->setPreference('focusmanager.testmode', true);

        $capabilities->setCapability(FirefoxDriver::PROFILE, $profile);

        return $capabilities;
    }

    /**
     * Set up Chrome/Chromium-specific capabilities
     * @param DesiredCapabilities $capabilities
     * @return DesiredCapabilities
     */
    protected function setupChromeCapabilities(DesiredCapabilities $capabilities)
    {
        return $capabilities;
    }

    /**
     * Set up Microsoft Edge-specific capabilities
     * @param DesiredCapabilities $capabilities
     * @return DesiredCapabilities
     */
    protected function setupMicrosoftEdgeCapabilities(DesiredCapabilities $capabilities)
    {
        return $capabilities;
    }

    /**
     * Set up Internet Explorer-specific capabilities
     * @param DesiredCapabilities $capabilities
     * @return DesiredCapabilities
     */
    protected function setupInternetExplorerCapabilities(DesiredCapabilities $capabilities)
    {
        // Clears cache, cookies, history, and saved form data of MSIE.
        $capabilities->setCapability('ie.ensureCleanSession', true);

        return $capabilities;
    }

    /**
     * Set up Safari-specific capabilities
     * @param DesiredCapabilities $capabilities
     * @return DesiredCapabilities
     */
    protected function setupSafariCapabilities(DesiredCapabilities $capabilities)
    {
        return $capabilities;
    }

    /**
     * Set up PhantomJS-specific capabilities
     * @param DesiredCapabilities $capabilities
     * @return DesiredCapabilities
     */
    protected function setupPhantomjsCapabilities(DesiredCapabilities $capabilities)
    {
        return $capabilities;
    }
}
