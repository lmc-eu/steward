<?php

namespace Lmc\Steward\Selenium;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

class SeleniumServerAdapterTest extends TestCase
{
    use PHPMock;

    /** @var SeleniumServerAdapter */
    protected $adapter;
    /** @var string */
    protected $serverUrl = 'http://selenium.local:1337';

    protected function setUp()
    {
        $this->adapter = new SeleniumServerAdapter($this->serverUrl);
    }

    public function testShouldGetParsedServerUrlParts()
    {
        $adapter = new SeleniumServerAdapter('https://user:pass@host:1337/selenium?foo=bar');

        $this->assertSame(
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 1337,
                'user' => 'user',
                'pass' => 'pass',
                'path' => '/selenium',
                'query' => 'foo=bar',
            ],
            $adapter->getServerUrlParts()
        );
    }

    /**
     * @dataProvider provideServerUrl
     * @param string $providedServerUrl
     * @param string $expectedServerUrl
     */
    public function testShouldGetServerUrlWithDefaultPortIfNeeded($providedServerUrl, $expectedServerUrl)
    {
        $adapter = new SeleniumServerAdapter($providedServerUrl);

        $this->assertSame($expectedServerUrl, $adapter->getServerUrl());
    }

    /**
     * @return array[]
     */
    public function provideServerUrl()
    {
        return [
            'protocol, host and port' => ['http://foo:80', 'http://foo:80'],
            'local URL with specified port should keep it' => ['http://localhost:4444', 'http://localhost:4444'],
            'local URL without port should use 4444' => ['http://localhost', 'http://localhost:4444'],
            'cloud service URL with port should keep it' =>
                ['http://foo:bar@ondemand.saucelabs.com:1337', 'http://foo:bar@ondemand.saucelabs.com:1337'],
            'Sauce Labs cloud service without port should use 80' =>
                ['http://foo:bar@ondemand.saucelabs.com', 'http://foo:bar@ondemand.saucelabs.com:80'],
            'BrowserStack cloud service without port should use 80' =>
                ['http://foo:bar@hub-cloud.browserstack.com', 'http://foo:bar@hub-cloud.browserstack.com:80'],
            'TestingBot cloud service without port should use 80' =>
                ['http://foo:bar@hub.testingbot.com', 'http://foo:bar@hub.testingbot.com:80'],
            'non-cloud URL without port should use 4444' => ['http://foo.com', 'http://foo.com:4444'],
            'username and host should get deault port' => ['http://user@foo', 'http://user@foo:4444'],
            'all parts' =>
                ['https://user:pass@host:1337/selenium?foo=bar', 'https://user:pass@host:1337/selenium?foo=bar'],
            'all parts expects port should get default port' =>
                ['https://user:pass@host/selenium?foo=bar', 'https://user:pass@host:4444/selenium?foo=bar'],
        ];
    }

    /**
     * @dataProvider provideInvalidServerUrl
     * @param string $serverUrl
     */
    public function testShouldThrowExceptionIfInvalidServerUrlIsGiven($serverUrl)
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provided Selenium server URL "' . $serverUrl . '" is invalid');

        new SeleniumServerAdapter($serverUrl);
    }

    /**
     * @return array[]
     */
    public function provideInvalidServerUrl()
    {
        return [
            ['http://'],
            [''],
            ['foo'],
        ];
    }

    public function testShouldReturnTrueIfUrlIsAccessible()
    {
        $dummyResource = fopen(__FILE__, 'r');

        $fsockopenMock = $this->getFunctionMock(__NAMESPACE__, 'fsockopen');
        $fsockopenMock->expects($this->once())
            ->with('selenium.local', '1337')
            ->willReturn($dummyResource);

        $this->assertTrue($this->adapter->isAccessible());
    }

    public function testShouldReturnFalseIfConnectionIsRefused()
    {
        $fsockopenMock = $this->getFunctionMock(__NAMESPACE__, 'fsockopen');
        $fsockopenMock->expects($this->once())
            ->with('selenium.local', '1337')
            ->willReturnCallback(function ($host, $port, &$connectionErrorNo, &$connectionError, $timeout) {
                $connectionErrorNo = 111;
                $connectionError = 'Connection refused';
            });

        $this->assertFalse($this->adapter->isAccessible());
        $this->assertEquals('Connection refused', $this->adapter->getLastError());
    }

    public function testShouldReturnFalseIfTheServerDoesNotRespondToStatusUrl()
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status')
            ->willReturn(false);

        $this->assertFalse($this->adapter->isSeleniumServer());
        $this->assertEquals('error reading server response', $this->adapter->getLastError());
    }

    public function testShouldReturnJsonErrorDescriptionIfTheServerResponseIsNotJson()
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status')
            ->willReturn('THIS IS NOT JSON');

        $this->assertFalse($this->adapter->isSeleniumServer());
        $this->assertRegExp('/^error parsing server JSON response \(.+\)$/', $this->adapter->getLastError());
    }

    public function testShouldReturnTrueIfServerRespondsWithJsonInNoGridMode()
    {
        $response = file_get_contents(__DIR__ . '/Fixtures/response-standalone.json');
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status')
            ->willReturn($response);

        $this->assertTrue($this->adapter->isSeleniumServer());
        $this->assertEmpty($this->adapter->getCloudService());
        $this->assertEmpty($this->adapter->getLastError());
    }

    public function testShouldReturnTrueIfServerRespondsWithJsonInGridMode()
    {
        $response = file_get_contents(__DIR__ . '/Fixtures/response-grid.json');
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status')
            ->willReturn($response);

        $this->assertTrue($this->adapter->isSeleniumServer());
        $this->assertEmpty($this->adapter->getCloudService());
        $this->assertEmpty($this->adapter->getLastError());
    }

    public function testShouldConnectToTheServerOnlyOnceWhenAttemptingToGetCloudServiceName()
    {
        $response = file_get_contents(__DIR__ . '/Fixtures/response-standalone.json');
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status')
            ->willReturn($response);

        $this->assertEmpty($this->adapter->getCloudService());
        $this->assertEmpty($this->adapter->getCloudService());
    }

    public function testShouldThrowExceptionWhenGettingCloudServiceNameButTheServerResponseIsInvalid()
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status')
            ->willReturn('THIS IS NOT JSON');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageRegExp(
            '/^Unable to connect to remote server: error parsing server JSON response \(.+\)$/'
        );

        $this->assertEmpty($this->adapter->getCloudService());
    }

    /**
     * @dataProvider provideCloudServiceResponse
     * @param string $responseData
     * @param string $expectedCloudService
     */
    public function testShouldDetectCloudService($responseData, $expectedCloudService)
    {
        $adapter = new SeleniumServerAdapter('http://such.cloud:80');
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with('http://such.cloud:80/wd/hub/status')
            ->willReturn($responseData);

        $this->assertSame($expectedCloudService, $adapter->getCloudService());
    }

    /**
     * @return array[]
     */
    public function provideCloudServiceResponse()
    {
        $responseSauceLabs = file_get_contents(__DIR__ . '/Fixtures/response-saucelabs.json');
        $responseBrowserStack = file_get_contents(__DIR__ . '/Fixtures/response-browserstack.json');
        $responseTestingBot = file_get_contents(__DIR__ . '/Fixtures/response-testingbot.json');
        $responseStandalone = file_get_contents(__DIR__ . '/Fixtures/response-standalone.json');
        $responseLocalGrid = file_get_contents(__DIR__ . '/Fixtures/response-grid.json');

        return [
            // $responseData, $expectedCloudService
            'Sauce Labs' => [$responseSauceLabs, SeleniumServerAdapter::CLOUD_SERVICE_SAUCELABS],
            'BrowserStack' => [$responseBrowserStack, SeleniumServerAdapter::CLOUD_SERVICE_BROWSERSTACK],
            'TestingBot' => [$responseTestingBot, SeleniumServerAdapter::CLOUD_SERVICE_TESTINGBOT],
            'non-cloud local standalone server' => [$responseStandalone, ''],
            'non-cloud local grid' => [$responseLocalGrid, ''],
        ];
    }

    /**
     * @dataProvider provideSessionExecutorResponse
     * @param string $responseData
     * @param string $expectedSessionExecutor
     */
    public function testShouldGetSessionExecutor($responseData, $expectedSessionExecutor)
    {
        $adapter = new SeleniumServerAdapter('http://127.0.0.1:4444');
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with('http://127.0.0.1:4444/grid/api/testsession?session=4f1bebc2-667e-4b99-b16a-ff36221a20b3')
            ->willReturn($responseData);

        $this->assertSame(
            $expectedSessionExecutor,
            $adapter->getSessionExecutor('4f1bebc2-667e-4b99-b16a-ff36221a20b3')
        );
    }

    /**
     * @return array[]
     */
    public function provideSessionExecutorResponse()
    {
        $responseExecutorFound = file_get_contents(__DIR__ . '/Fixtures/testsession-found.json');
        $responseExecutorNotFound = file_get_contents(__DIR__ . '/Fixtures/testsession-not-found.json');
        $responseInvalid = file_get_contents(__DIR__ . '/Fixtures/testsession-invalid-response.txt');

        return [
            // $responseData, $expectedSessionExecutor
            'Executor for session was found' => [$responseExecutorFound, 'http://10.1.255.241:5555'],
            'Executor for session was not found' => [$responseExecutorNotFound, ''],
            'Invalid response by API to get session information' => [$responseInvalid, ''],
            'Empty response' => ['', ''],
        ];
    }
}
