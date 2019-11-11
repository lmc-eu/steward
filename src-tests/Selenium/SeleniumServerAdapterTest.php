<?php declare(strict_types=1);

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

    protected function setUp(): void
    {
        $this->adapter = new SeleniumServerAdapter($this->serverUrl);
    }

    public function testShouldGetParsedServerUrlParts(): void
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
     */
    public function testShouldGetServerUrl(string $providedServerUrl, string $expectedServerUrl): void
    {
        $adapter = new SeleniumServerAdapter($providedServerUrl);

        $this->assertSame($expectedServerUrl, $adapter->getServerUrl());
    }

    /**
     * @return array[]
     */
    public function provideServerUrl(): array
    {
        return [
            'protocol, host and port => should not be changed' => ['http://foo:80', 'http://foo:80'],
            'trailing slash should be removed' => ['http://localhost:4444/wd/hub/', 'http://localhost:4444/wd/hub'],
            'local URL with specified port should keep it' => ['http://localhost:4444', 'http://localhost:4444'],
            'local URL without port should use 4444' => ['http://localhost', 'http://localhost:4444'],
            'HTTPS URL without port should use 443' => ['https://foo.bar', 'https://foo.bar:443'],
            'HTTPS on different port than 443' => ['https://foo.bar:666', 'https://foo.bar:666'],
            'cloud service URL with port should keep it' =>
                ['http://foo:bar@ondemand.saucelabs.com:1337', 'http://foo:bar@ondemand.saucelabs.com:1337'],
            'Sauce Labs cloud service without port should use 80' =>
                ['http://foo:bar@ondemand.saucelabs.com', 'http://foo:bar@ondemand.saucelabs.com:80'],
            'Sauce Labs HTTPS cloud service without port should use 443' =>
                ['https://foo:bar@ondemand.saucelabs.com', 'https://foo:bar@ondemand.saucelabs.com:443'],
            'BrowserStack cloud service without port should use 80' =>
                ['http://foo:bar@hub-cloud.browserstack.com', 'http://foo:bar@hub-cloud.browserstack.com:80'],
            'TestingBot cloud service without port should use 80' =>
                ['http://foo:bar@hub.testingbot.com', 'http://foo:bar@hub.testingbot.com:80'],
            'non-cloud URL without port should use 4444' => ['http://foo.com', 'http://foo.com:4444'],
            'username and host should get default port' => ['http://user@foo', 'http://user@foo:4444'],
            'all url parts' =>
                ['http://user:pass@host:1337/wd/hub?foo=bar', 'http://user:pass@host:1337/wd/hub?foo=bar'],
            'all parts expects port should get default port' =>
                ['http://user:pass@host/wd/hub?foo=bar', 'http://user:pass@host:4444/wd/hub?foo=bar'],
        ];
    }

    /**
     * @dataProvider provideInvalidServerUrl
     */
    public function testShouldThrowExceptionIfInvalidServerUrlIsGiven(string $serverUrl): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provided Selenium server URL "' . $serverUrl . '" is invalid');

        new SeleniumServerAdapter($serverUrl);
    }

    /**
     * @return array[]
     */
    public function provideInvalidServerUrl(): array
    {
        return [
            ['http://'],
            [''],
            ['foo'],
        ];
    }

    public function testShouldReturnTrueIfUrlIsAccessible(): void
    {
        $dummyResource = fopen(__FILE__, 'rb');

        $fsockopenMock = $this->getFunctionMock(__NAMESPACE__, 'fsockopen');
        $fsockopenMock->expects($this->once())
            ->with('selenium.local', '1337')
            ->willReturn($dummyResource);

        $this->assertTrue($this->adapter->isAccessible());
    }

    public function testShouldReturnFalseIfConnectionIsRefused(): void
    {
        $fsockopenMock = $this->getFunctionMock(__NAMESPACE__, 'fsockopen');
        $fsockopenMock->expects($this->once())
            ->with('selenium.local', '1337')
            ->willReturnCallback(function ($host, $port, &$connectionErrorNo, &$connectionError, $timeout): void {
                $connectionErrorNo = 111;
                $connectionError = 'Connection refused';
            });

        $this->assertFalse($this->adapter->isAccessible());
        $this->assertEquals('Connection refused', $this->adapter->getLastError());
    }

    public function testShouldReturnFalseIfTheServerDoesNotRespondToStatusUrl(): void
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/status')
            ->willReturn(false);

        $this->assertFalse($this->adapter->isSeleniumServer());
        $this->assertEquals('error reading server response', $this->adapter->getLastError());
    }

    public function testShouldReturnJsonErrorDescriptionIfTheServerResponseIsNotJson(): void
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/status')
            ->willReturn('THIS IS NOT JSON');

        $this->assertFalse($this->adapter->isSeleniumServer());
        $this->assertRegExp('/^error parsing server JSON response \(.+\)$/', $this->adapter->getLastError());
    }

    /**
     * @dataProvider provideValidStatusResponses
     */
    public function testShouldDetectSeleniumServer(string $responseFixtureFile, string $expectedCloudService): void
    {
        $response = file_get_contents($responseFixtureFile);
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/status')
            ->willReturn($response);

        $this->assertTrue($this->adapter->isSeleniumServer());
        $this->assertSame($this->adapter->getCloudService(), $expectedCloudService);
        $this->assertEmpty($this->adapter->getLastError());
    }

    /**
     * @return array[]
     */
    public function provideValidStatusResponses(): array
    {
        return [
            // $responseData, $expectedCloudService
            'standalone W3C server (in ready state)' => [__DIR__ . '/Fixtures/response-standalone-w3c-ready.json', ''],
            'standalone W3C server (in not ready state - but tests could wait in queue)' => [
                __DIR__ . '/Fixtures/response-standalone-w3c-not-ready.json',
                '',
            ],
            'standalone server v2' => [__DIR__ . '/Fixtures/response-standalone-v2.json', ''],
            'standalone local grid v2' => [__DIR__ . '/Fixtures/response-standalone-hub-v2.json', ''],
            'Sauce Labs cloud' =>
                [__DIR__ . '/Fixtures/response-saucelabs.json', SeleniumServerAdapter::CLOUD_SERVICE_SAUCELABS],
            'BrowserStack cloud' =>
                [__DIR__ . '/Fixtures/response-browserstack.json', SeleniumServerAdapter::CLOUD_SERVICE_BROWSERSTACK],
            'TestingBot cloud' =>
                [__DIR__ . '/Fixtures/response-testingbot.json', SeleniumServerAdapter::CLOUD_SERVICE_TESTINGBOT],
        ];
    }

    public function testShouldConnectToTheServerOnlyOnceWhenAttemptingToGetCloudServiceName(): void
    {
        $response = file_get_contents(__DIR__ . '/Fixtures/response-standalone-v2.json');
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/status')
            ->willReturn($response);

        $this->assertEmpty($this->adapter->getCloudService());
        $this->assertEmpty($this->adapter->getCloudService());
    }

    public function testShouldThrowExceptionWhenGettingCloudServiceNameButTheServerResponseIsInvalid(): void
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/status')
            ->willReturn('THIS IS NOT JSON');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageRegExp(
            '/^Unable to connect to remote server: error parsing server JSON response \(.+\)$/'
        );

        $this->assertEmpty($this->adapter->getCloudService());
    }

    /**
     * @dataProvider provideSessionExecutorResponse
     */
    public function testShouldGetSessionExecutor(string $responseData, string $expectedSessionExecutor): void
    {
        $adapter = new SeleniumServerAdapter('http://127.0.0.1:4444/wd/hub');
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
    public function provideSessionExecutorResponse(): array
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
