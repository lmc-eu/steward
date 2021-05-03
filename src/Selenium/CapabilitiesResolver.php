<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

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

    public function resolveDesiredCapabilities(AbstractTestCase $test): DesiredCapabilities
    {
        $capabilities = new DesiredCapabilities(
            [
                WebDriverCapabilityType::BROWSER_NAME => $this->config->browserName,
                WebDriverCapabilityType::PLATFORM => WebDriverPlatform::ANY,
                'name' => get_class($test) . '::' . $test->getName(),
            ]
        );

        if (!empty($this->config->capability)) {
            $extraCapabilities = json_decode($this->config->capability, false, 512, JSON_THROW_ON_ERROR);
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
     * @internal
     */
    public function setCiDetector(CiDetector $ciDetector): void
    {
        $this->ciDetector = $ciDetector;
    }

    /**
     * Setup capabilities specific for continuous integration server
     */
    protected function setupCiCapabilities(
        DesiredCapabilities $capabilities,
        AbstractTestCase $test
    ): DesiredCapabilities {
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
     * Setup Steward-default capabilities for specific browsers.
     * To setup custom default capabilities for each browser, implement custom capabilities resolver, see
     * https://github.com/lmc-eu/steward/wiki/Set-custom-capabilities#implement-custom-capabilities-resolver
     */
    protected function setupBrowserSpecificCapabilities(
        DesiredCapabilities $capabilities,
        string $browser
    ): DesiredCapabilities {
        switch ($browser) {
            case WebDriverBrowserType::IE:
                $capabilities = $this->setupInternetExplorerCapabilities($capabilities);
                break;
        }

        return $capabilities;
    }

    protected function setupInternetExplorerCapabilities(DesiredCapabilities $capabilities): DesiredCapabilities
    {
        // Clears cache, cookies, history, and saved form data of MSIE.
        $capabilities->setCapability('ie.ensureCleanSession', true);

        return $capabilities;
    }
}
