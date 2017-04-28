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

final class CapabilitiesResolver
{
    /** @var ConfigProvider */
    protected $config;
    /** @var CiDetector */
    protected $ciDetector;
    /** @var CustomCapabilitiesResolverInterface|null */
    protected $customCapabilitiesResolver;

    public function __construct(ConfigProvider $config)
    {
        $this->config = $config;

        if (!empty($config->capabilitiesResolver)) {
            $this->customCapabilitiesResolver = new $config->capabilitiesResolver($config);
        }

        $this->ciDetector = new CiDetector();
    }

    /**
     * @param AbstractTestCase $test
     * @return DesiredCapabilities
     */
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
            $capabilities = $this->setupCiCapabilities($capabilities, $test);
        }

        $capabilities = $this->setupBrowserSpecificCapabilities($capabilities, $this->config->browserName);

        if ($this->customCapabilitiesResolver !== null) {
            $capabilities = $this->customCapabilitiesResolver->resolveDesiredCapabilities($test, $capabilities);
        }

        return $capabilities;
    }

    /**
     * @param AbstractTestCase $test
     * @return DesiredCapabilities
     */
    public function resolveRequiredCapabilities(AbstractTestCase $test)
    {
        $capabilities = new DesiredCapabilities();

        if ($this->customCapabilitiesResolver !== null) {
            $capabilities = $this->customCapabilitiesResolver->resolveRequiredCapabilities($test, $capabilities);
        }

        return $capabilities;
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
     * Setup capabilities specific for continuous integration server
     * @param DesiredCapabilities $capabilities
     * @param AbstractTestCase $test
     * @return DesiredCapabilities
     */
    protected function setupCiCapabilities(DesiredCapabilities $capabilities, AbstractTestCase $test)
    {
        $ci = $this->ciDetector->detect();
        $capabilities->setCapability(
            'build',
            $this->config->env . '-' . $ci->getBuildNumber()
        );
        $capabilities->setCapability(
            'tags',
            [$this->config->env, $ci->getCiName(), get_class($test)]
        );

        return $capabilities;
    }

    /**
     * Setup browser-specific custom capabilities.
     * @param DesiredCapabilities $capabilities
     * @param string $browser Browser name
     * @return DesiredCapabilities
     */
    protected function setupBrowserSpecificCapabilities(DesiredCapabilities $capabilities, $browser)
    {
        switch ($browser) {
            case WebDriverBrowserType::FIREFOX:
                $capabilities = $this->setupFirefoxCapabilities($capabilities);
                break;
            case WebDriverBrowserType::IE:
                $capabilities = $this->setupInternetExplorerCapabilities($capabilities);
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
}
