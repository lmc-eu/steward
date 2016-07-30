<?php

namespace Lmc\Steward\Selenium;

use phpmock\phpunit\PHPMock;

class DownloaderTest extends \PHPUnit_Framework_TestCase
{
    use PHPMock;

    public function testShouldParseLatestVersion()
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->with('https://selenium-release.storage.googleapis.com')
            ->willReturn($releasesDummyResponse);

        $this->assertEquals('2.45.0', Downloader::getLatestVersion());
    }

    public function testShouldReturnFalseIfRequestToGetLatestVersionFailed()
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn(false);

        $this->assertFalse(Downloader::getLatestVersion());
    }

    public function testShouldReturnFalseIfRequestToGetLatestVersionReturnsInvalidXml()
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn('is not XML');

        $this->assertFalse(Downloader::getLatestVersion());
    }

    public function testShouldReturnFalseIfLatestVersionCannotBeFound()
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response-broken.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn($releasesDummyResponse);

        $this->assertFalse(Downloader::getLatestVersion());
    }

    /**
     * @group integration
     */
    public function testShouldReadLatestStableVersionFromTheStorageUrl()
    {
        $latestVersion = Downloader::getLatestVersion();
        $this->assertInternalType('string', $latestVersion);
        $this->assertRegExp('/^\d+\.\d+\.\d+$/', $latestVersion);
    }

    public function testShouldGetVersionSetBySetter()
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');

        $downloader->setVersion('1.33.7');
        $this->assertEquals('1.33.7', $downloader->getVersion());

        $downloader->setVersion('6.33.6');
        $this->assertEquals('6.33.6', $downloader->getVersion());
    }

    public function testShouldGetLatestVersionOfNoneVersionSpecified()
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->with('https://selenium-release.storage.googleapis.com')
            ->willReturn($releasesDummyResponse);

        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $this->assertEquals('2.45.0', $downloader->getVersion());
    }

    public function testShouldAssembleTargetFilePath()
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $downloader->setVersion('3.33.6');

        $this->assertEquals(__DIR__ . '/Fixtures/selenium-server-standalone-3.33.6.jar', $downloader->getFilePath());
    }

    public function testShouldAssembleUrlToDownload()
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $downloader->setVersion('3.66.3');

        $this->assertEquals(
            'https://selenium-release.storage.googleapis.com/3.66/selenium-server-standalone-3.66.3.jar',
            $downloader->getFileUrl()
        );
    }

    public function testShouldCheckIfFileWasAlreadyDownloaded()
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $downloader->setVersion('2.45.0');

        $this->assertTrue($downloader->isAlreadyDownloaded());

        $downloader->setVersion('2.66.6');
        $this->assertFalse($downloader->isAlreadyDownloaded());
    }

    public function testShouldStoreDownloadedFileToExpectedLocation()
    {
        // Mock getFileUrl() method to return URL to fixtures on filesystem
        /** @var Downloader|\PHPUnit_Framework_MockObject_MockObject $downloader */
        $downloader = $this->getMockBuilder(Downloader::class)
            ->setConstructorArgs([__DIR__ . '/Fixtures'])
            ->setMethods(['getFileUrl'])
            ->getMock();

        $downloader->expects($this->once())
            ->method('getFileUrl')
            ->willReturn(__DIR__ . '/Fixtures/dummy-file.jar');

        $downloader->setVersion('1.33.7');

        $expectedFile = __DIR__ . '/Fixtures/selenium-server-standalone-1.33.7.jar';
        $this->assertFileNotExists($expectedFile, 'File already exists, though it should be created only by the test');

        $this->assertEquals(9, $downloader->download());
        $this->assertFileExists($expectedFile);

        // Cleanup
        unlink($expectedFile);
    }

    public function testShouldCreateTargetDirectoryIfNotExists()
    {
        $expectedDirectory = __DIR__ . '/Fixtures/not/existing/directory';
        $this->assertFileNotExists(
            $expectedDirectory,
            'Directory already exists, though it should be created only by the test'
        );

        /** @var Downloader|\PHPUnit_Framework_MockObject_MockObject $downloader */
        $downloader = $this->getMockBuilder(Downloader::class)
            ->setConstructorArgs([$expectedDirectory])
            ->setMethods(['getFileUrl'])
            ->getMock();

        $downloader->expects($this->once())
            ->method('getFileUrl')
            ->willReturn(__DIR__ . '/Fixtures/dummy-file.jar');
        $downloader->setVersion('1.33.7');

        $downloader->download();

        // Directory should now be crated
        $this->assertFileExists($expectedDirectory);

        // Cleanup
        unlink($downloader->getFilePath());
        rmdir($expectedDirectory);
    }
}
