<?php declare(strict_types=1);

namespace Lmc\Steward\WebDriver;

use Facebook\WebDriver\Remote\JsonWireCompat;
use Facebook\WebDriver\Remote\WebDriverResponse;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverCommandExecutor;
use Lmc\Steward\ConfigHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RemoteWebDriverTest extends TestCase
{
    /** @var RemoteWebDriver|MockObject */
    protected $driver;
    /** @var WebDriverCommandExecutor|MockObject */
    protected $executorMock;

    protected function setUp(): void
    {
        // We need to create RemoteWebDriver instance without calling create(), and to mock its command executor,
        // so the requests to Selenium don't get actually called
        $this->driver = $this->getMockBuilder(RemoteWebDriver::class)
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $this->executorMock = $this->createMock(WebDriverCommandExecutor::class);
        $this->driver->setCommandExecutor($this->executorMock);
    }

    public function testShouldWriteLoadedUrlToLogEvenWithDisabledDebugMode(): void
    {
        $this->setDebugMode(false); // Make debug mode disabled

        $this->setUpExecutorToReturnResponse(new WebDriverResponse());

        $this->driver->get('http://foo.bar');

        $this->expectOutputRegex('/.*\[WebDriver\] Loading URL "http:\/\/foo.bar"/');
    }

    public function testShouldLogCommandsInDebugMode(): void
    {
        $this->setDebugMode(true); // Enable debug mode

        $response = new WebDriverResponse();
        $response->setValue([JsonWireCompat::WEB_DRIVER_ELEMENT_IDENTIFIER => []]);
        $this->setUpExecutorToReturnResponse($response);

        $this->driver->findElement(WebDriverBy::className('foo'));
        $this->driver->getTitle();

        $this->expectOutputRegex(
            '/.*Executing command "findElement" with params {"using":"class name","value":"foo"}/'
        );
        $this->expectOutputRegex('/.*Executing command "getTitle" with params \[\]/');
    }

    public function testShouldNotLogCommandsInNormalMode(): void
    {
        $this->setDebugMode(false); // Make debug mode disabled

        $response = new WebDriverResponse();
        $response->setValue([JsonWireCompat::WEB_DRIVER_ELEMENT_IDENTIFIER => []]);
        $this->setUpExecutorToReturnResponse($response);

        $this->driver->findElement(WebDriverBy::className('foo'));
        $this->driver->getTitle();

        $this->expectOutputString('');
    }

    protected function setDebugMode(bool $enabled): void
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['DEBUG'] = $enabled ? 1 : 0;
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();
    }

    protected function setUpExecutorToReturnResponse(WebDriverResponse $response): void
    {
        $this->executorMock->expects($this->any())
            ->method('execute')
            ->willReturn($response);
    }
}
