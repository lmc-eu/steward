<?php

namespace Lmc\Steward\Test;

use phpmock\phpunit\PHPMock;

/**
 * @group omg
 */
class SeleniumAdapterTest extends \PHPUnit_Framework_TestCase
{
    use PHPMock;

    /** @var SeleniumAdapter */
    protected $adapter;
    /** @var string */
    protected $serverUrl = 'http://selenium.local:1337';

    protected function setUp()
    {
        $this->adapter = new SeleniumAdapter();
    }

    public function testShouldReturnTrueIfUrlIsAccessible()
    {
        $dummyResource = fopen(__FILE__, 'r');

        $fsockopenMock = $this->getFunctionMock(__NAMESPACE__, 'fsockopen');
        $fsockopenMock->expects($this->once())
            ->with('selenium.local', '1337')
            ->willReturn($dummyResource);

        $this->assertTrue($this->adapter->isAccessible($this->serverUrl));
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

        $this->assertFalse($this->adapter->isAccessible($this->serverUrl));
        $this->assertEquals('Connection refused', $this->adapter->getLastError());
    }

    public function testShouldReturnFalseIfTheServerDoesNotRespondToStatusUrl()
    {
        $fileFetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileFetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status/')
            ->willReturn(false);

        $this->assertFalse($this->adapter->isSeleniumServer($this->serverUrl));
        $this->assertEquals('error reading server response', $this->adapter->getLastError());
    }

    public function testShouldReturnJsonErrorDescriptionIfTheServerResponseIsNotJson()
    {
        $fileFetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileFetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status/')
            ->willReturn('THIS IS NOT JSON');

        $this->assertFalse($this->adapter->isSeleniumServer($this->serverUrl));
        $this->assertRegExp('/^error parsing server JSON response \(.+\)$/', $this->adapter->getLastError());
    }

    public function testShouldReturnTrueIfServerRespondsWithJsonInNoGridMode()
    {
        $fileFetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileFetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status/')
            ->willReturn(
                '{"sessionId":null,"status":0,"state":"success",'
                . '"value":{"build":{"version":"2.45.0","revision":"5017cb8","time":"2015-02-26 23:59:50"},'
                . '"os":{"name":"Linux","arch":"i386","version":"3.17.2-1-custom"},'
                . '"java":{"version":"1.7.0_79"}},"class":"org.openqa.selenium.remote.Response","hCode":27226235}'
            );

        $this->assertTrue($this->adapter->isSeleniumServer($this->serverUrl));
        $this->assertEmpty($this->adapter->getLastError());
    }

    public function testShouldReturnTrueIfServerRespondsWithJsonInGridMode()
    {
        $fileFetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileFetContentsMock->expects($this->once())
            ->with($this->serverUrl . '/wd/hub/status/')
            ->willReturn(
                '{"status":13,"value":{"message":"Session [(null externalkey)] not available and is not among the'
                . ' last 1000 terminated sessions.\nActive sessions are[]",'
                . '"class":"org.openqa.grid.common.exception.GridException",'
                . '"stackTrace":[{"fileName":"Thread.java","className":"java.lang.Thread","methodName":"run",'
                . '"lineNumber":745}]}}'
            );

        $this->assertTrue($this->adapter->isSeleniumServer($this->serverUrl));
        $this->assertEmpty($this->adapter->getLastError());
    }
}
