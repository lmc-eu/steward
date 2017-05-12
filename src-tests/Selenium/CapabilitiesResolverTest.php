<?php

namespace Lmc\Steward\Selenium;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Facebook\WebDriver\WebDriverPlatform;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\ConfigProviderHelper;
use Lmc\Steward\Selenium\Fixtures\CapabilitiesResolverFixture;
use Lmc\Steward\Test\AbstractTestCase;
use OndraM\CiDetector\Ci\Jenkins;
use OndraM\CiDetector\CiDetector;
use PHPUnit\Framework\TestCase;

/**
 * @covers Lmc\Steward\Selenium\CapabilitiesResolver
 */
class CapabilitiesResolverTest extends TestCase
{
    /**
     * @dataProvider provideBrowsers
     * @param string $browser
     */
    public function testShouldResolveBasicDesiredCapabilities($browser, callable $extraCallback = null)
    {
        /** @var AbstractTestCase $test */
        $test = $this->getMockForAbstractClass(AbstractTestCase::class, ['name'], 'FooBarTest');

        $configMock = new ConfigProviderHelper(['browserName' => $browser]);

        $resolver = new CapabilitiesResolver($configMock);
        $resolver->setCiDetector($this->createConfiguredMock(CiDetector::class, ['isCiDetected' => false]));

        $desiredCapabilities = $resolver->resolveDesiredCapabilities($test);
        $this->assertInstanceOf(DesiredCapabilities::class, $desiredCapabilities);

        $desiredCapabilitiesArray = $desiredCapabilities->toArray();
        $this->assertSame($browser, $desiredCapabilitiesArray['browserName']);
        $this->assertSame(WebDriverPlatform::ANY, $desiredCapabilitiesArray['platform']);
        $this->assertSame('FooBarTest::name', $desiredCapabilitiesArray['name']);

        if ($extraCallback !== null) {
            $extraCallback($desiredCapabilitiesArray);
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

        $configMock = new ConfigProviderHelper(
            [
                'browserName' => WebDriverBrowserType::FIREFOX,
                'env' => 'staging',
            ]
        );

        $ciMock = $this->createConfiguredMock(Jenkins::class, ['getBuildNumber' => 1337, 'getCiName' => 'Jenkins']);
        $ciDetectorMock = $this->createConfiguredMock(
            CiDetector::class,
            ['isCiDetected' => true, 'detect' => $ciMock]
        );

        $resolver = new CapabilitiesResolver($configMock);
        $resolver->setCiDetector($ciDetectorMock);

        $desiredCapabilities = $resolver->resolveDesiredCapabilities($test);
        $desiredCapabilitiesArray = $desiredCapabilities->toArray();
        $this->assertSame('staging-1337', $desiredCapabilitiesArray['build']);
        $this->assertEquals(['staging', 'Jenkins', 'FooBarTest'], $desiredCapabilitiesArray['tags']);
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

    public function testShouldCallCustomCapabilitiesResolverIfDefined()
    {
        /** @var AbstractTestCase $test */
        $test = $this->getMockForAbstractClass(AbstractTestCase::class, ['name'], 'FooBarTest');

        $configMock = new ConfigProviderHelper(
            [
                'browserName' => 'firefox',
                'capabilitiesResolver' => CapabilitiesResolverFixture::class,
            ]
        );

        $resolver = new CapabilitiesResolver($configMock);
        $resolver->setCiDetector($this->createConfiguredMock(CiDetector::class, ['isCiDetected' => false]));

        $desiredCapabilities = $resolver->resolveDesiredCapabilities($test);
        $requiredCapabilities = $resolver->resolveRequiredCapabilities($test);

        $this->assertNotEmpty(
            $desiredCapabilities->getCapability(CapabilitiesResolverFixture::CUSTOM_DESIRED_CAPABILITY)
        );

        $this->assertNotEmpty(
            $requiredCapabilities->getCapability(CapabilitiesResolverFixture::CUSTOM_REQUIRED_CAPABILITY)
        );
    }
}
