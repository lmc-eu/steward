<?php

namespace Lmc\Steward\Selenium;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Facebook\WebDriver\WebDriverPlatform;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Test\AbstractTestCase;
use OndraM\CiDetector\Ci\Jenkins;
use OndraM\CiDetector\CiDetector;

/**
 * @covers Lmc\Steward\Selenium\CapabilitiesResolver
 */
class CapabilitiesResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideBrowsers
     * @param string $browser
     */
    public function testShouldResolveBasicDesiredCapabilities($browser, callable $extraCallback = null)
    {
        /** @var AbstractTestCase $test */
        $test = $this->getMockForAbstractClass(AbstractTestCase::class, ['name'], 'FooBarTest');

        $configMock = $this->createMock(ConfigProvider::class);
        $configMock->browserName = $browser;

        $resolver = new CapabilitiesResolver($configMock);
        $resolver->setCiDetector($this->createConfiguredMock(CiDetector::class, ['isCiDetected' => false]));

        $requiredCapabilities = $resolver->resolveDesiredCapabilities($test);
        $this->assertInstanceOf(DesiredCapabilities::class, $requiredCapabilities);

        $requiredCapabilitiesArray = $requiredCapabilities->toArray();
        $this->assertSame($browser, $requiredCapabilitiesArray['browserName']);
        $this->assertSame(WebDriverPlatform::ANY, $requiredCapabilitiesArray['platform']);
        $this->assertSame('FooBarTest::name', $requiredCapabilitiesArray['name']);

        if ($extraCallback !== null) {
            $extraCallback($requiredCapabilitiesArray);
        }
    }

    /**
     * @return array[]
     */
    public function provideBrowsers()
    {
        return [
            [
                WebDriverBrowserType::FIREFOX,
                function ($capabilitiesArray) {
                    $this->assertNotEmpty($capabilitiesArray['firefox_profile']);
                },
            ],
            [WebDriverBrowserType::CHROME],
            [WebDriverBrowserType::MICROSOFT_EDGE],
            [
                WebDriverBrowserType::IE,
                function ($capabilitiesArray) {
                    $this->assertSame(true, $capabilitiesArray['ie.ensureCleanSession']);
                },
            ],
            [WebDriverBrowserType::SAFARI],
            [WebDriverBrowserType::PHANTOMJS],
        ];
    }

    public function testShouldResolveExtraDesiredCapabilitiesOnCiServer()
    {
        /** @var AbstractTestCase $test */
        $test = $this->getMockForAbstractClass(AbstractTestCase::class, ['name'], 'FooBarTest');

        $configMock = $this->createMock(ConfigProvider::class);
        $configMock->browserName = WebDriverBrowserType::FIREFOX;
        $configMock->env = 'staging';

        $ciMock = $this->createConfiguredMock(Jenkins::class, ['getBuildNumber' => 1337, 'getCiName' => 'Jenkins']);
        $ciDetectorMock = $this->createConfiguredMock(
            CiDetector::class,
            ['isCiDetected' => true, 'detect' => $ciMock]
        );

        $resolver = new CapabilitiesResolver($configMock);
        $resolver->setCiDetector($ciDetectorMock);

        $requiredCapabilities = $resolver->resolveDesiredCapabilities($test);
        $requiredCapabilitiesArray = $requiredCapabilities->toArray();
        $this->assertSame('staging-1337', $requiredCapabilitiesArray['build']);
        $this->assertEquals(['staging', 'Jenkins', 'FooBarTest'], $requiredCapabilitiesArray['tags']);
    }

    public function testShouldResolveRequiredCapabilities()
    {
        /** @var AbstractTestCase $test */
        $test = $this->getMockForAbstractClass(AbstractTestCase::class, ['name']);

        $configMock = $this->createMock(ConfigProvider::class);
        $resolver = new CapabilitiesResolver($configMock);

        $requiredCapabilities = $resolver->resolveRequiredCapabilities($test);

        // TODO: should be instance of WebDriverCapabilities interface in next php-webdriver major release
        $this->assertInstanceOf(DesiredCapabilities::class, $requiredCapabilities);
        $this->assertSame([], $requiredCapabilities->toArray());
    }
}
