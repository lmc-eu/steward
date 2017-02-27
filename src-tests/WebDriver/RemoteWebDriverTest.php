<?php

namespace Lmc\Steward\WebDriver;

use Facebook\WebDriver\WebDriverBy;
use Lmc\Steward\ConfigHelper;
use Lmc\Steward\WebDriver\Fixtures\DummyCommandExecutor;
use PHPUnit\Framework\TestCase;

class RemoteWebDriverTest extends TestCase
{
    /** @var RemoteWebDriver|\PHPUnit_Framework_MockObject_MockObject */
    protected $driver;

    public function setUp()
    {
        // We need to create RemoteWebDriver instance without calling create(), and to mock its command executor,
        // so the requests to Selenium don't get actually called
        $this->driver = $this->getMockBuilder(RemoteWebDriver::class)
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $this->driver->setCommandExecutor(new DummyCommandExecutor());
    }

    public function testShouldWriteLoadedUrlToLogEvenWithDisabledDebugMode()
    {
        $this->setDebugMode(false); // Make debug mode disabled

        $this->driver->get('http://foo.bar');

        $this->expectOutputRegex('/.*\[WebDriver\] Loading URL "http:\/\/foo.bar"/');
    }

    public function testShouldLogCommandsInDebugMode()
    {
        $this->setDebugMode(true); // Enable debug mode

        $this->driver->findElement(WebDriverBy::className('foo'));
        $this->driver->getTitle();

        $this->expectOutputRegex(
            '/.*Executing command "findElement" with params {"using":"class name","value":"foo"}/'
        );
        $this->expectOutputRegex('/.*Executing command "getTitle" with params \[\]/');
    }

    public function testShouldNotLogCommandsInNormalMode()
    {
        $this->setDebugMode(false); // Make debug mode disabled

        $this->driver->findElement(WebDriverBy::className('foo'));
        $this->driver->getTitle();

        $this->expectOutputString('');
    }

    /**
     * @param bool $enabled
     */
    protected function setDebugMode($enabled)
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['DEBUG'] = $enabled ? 1 : 0;
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();
    }
}
