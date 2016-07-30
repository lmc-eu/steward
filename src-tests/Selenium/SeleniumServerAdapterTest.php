<?php

namespace Lmc\Steward\Selenium;

use phpmock\phpunit\PHPMock;

class SeleniumServerAdapterTest extends \PHPUnit_Framework_TestCase
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
     * @dataProvider serverUrlProvider
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
    public function serverUrlProvider()
    {
        return [
            'protocol, host and port' => ['http://foo:80', 'http://foo:80'],
            'local URL with specified port should keep it' => ['http://localhost:4444', 'http://localhost:4444'],
            'local URL without port should use 4444' => ['http://localhost', 'http://localhost:4444'],
            'cloud service URL with port should keep it' =>
                ['http://foo:bar@ondemand.saucelabs.com:1337', 'http://foo:bar@ondemand.saucelabs.com:1337'],
            'cloud service URL without port should use 80' =>
                ['http://foo:bar@ondemand.saucelabs.com', 'http://foo:bar@ondemand.saucelabs.com:80'],
            'non-cloud URL without port should use 4444' => ['http://foo.com', 'http://foo.com:4444'],
            'username and host should get deault port' => ['http://user@foo', 'http://user@foo:4444'],
            'all parts' =>
                ['https://user:pass@host:1337/selenium?foo=bar', 'https://user:pass@host:1337/selenium?foo=bar'],
            'all parts expects port should get default port' =>
                ['https://user:pass@host/selenium?foo=bar', 'https://user:pass@host:4444/selenium?foo=bar'],
        ];
    }

    /**
     * @dataProvider invalidServerUrlProvider
     * @param string $serverUrl
     */
    public function testShouldThrowExceptionIfInvalidServerUrlIsGiven($serverUrl)
    {
        $this->setExpectedException(
            \RuntimeException::class,
            'Provided Selenium server URL "' . $serverUrl . '" is invalid'
        );

        new SeleniumServerAdapter($serverUrl);
    }

    /**
     * @return array[]
     */
    public function invalidServerUrlProvider()
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
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status')
            ->willReturn(
                '{"sessionId":null,"status":0,"state":"success",'
                . '"value":{"build":{"version":"2.45.0","revision":"5017cb8","time":"2015-02-26 23:59:50"},'
                . '"os":{"name":"Linux","arch":"i386","version":"3.17.2-1-custom"},'
                . '"java":{"version":"1.7.0_79"}},"class":"org.openqa.selenium.remote.Response","hCode":27226235}'
            );

        $this->assertTrue($this->adapter->isSeleniumServer());
        $this->assertEmpty($this->adapter->getLastError());
    }

    public function testShouldReturnTrueIfServerRespondsWithJsonInGridMode()
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status')
            ->willReturn(
                '{"status":13,"value":{"message":"Session [(null externalkey)] not available and is not among the'
                . ' last 1000 terminated sessions.\nActive sessions are[]",'
                . '"class":"org.openqa.grid.common.exception.GridException",'
                . '"stackTrace":[{"fileName":"Thread.java","className":"java.lang.Thread","methodName":"run",'
                . '"lineNumber":745}]}}'
            );

        $this->assertTrue($this->adapter->isSeleniumServer());
        $this->assertEmpty($this->adapter->getLastError());
    }

    /**
     * @dataProvider cloudServiceProvider
     * @param string $serverUrl
     * @param bool $isCloudService
     */
    public function testShouldDetectCloudServices($serverUrl, $isCloudService)
    {
        $adapter = new SeleniumServerAdapter($serverUrl);

        $this->assertSame($isCloudService, $adapter->isCloudService());
    }

    /**
     * @return array[]
     */
    public function cloudServiceProvider()
    {
        return [
            'SauceLabs cloud service' => ['http://foo:bar@ondemand.saucelabs.com:80', true],
            'BrowserStack cloud service' => ['http://bar:baz@hub-cloud.browserstack.com:80', true],
            'non-cloud host' => ['http://foobar.com', false],
        ];
    }
}
