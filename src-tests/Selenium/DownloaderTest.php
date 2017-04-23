<?php

namespace Lmc\Steward\Selenium;

use Assert\InvalidArgumentException;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

class DownloaderTest extends TestCase
{
    use PHPMock;

    public function testShouldParseLatestVersion()
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->with('https://selenium-release.storage.googleapis.com')
            ->willReturn($releasesDummyResponse);

        $this->assertEquals('3.0.0-beta1', Downloader::getLatestVersion());
    }

    public function testShouldReturnNullIfRequestToGetLatestVersionFailed()
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn(false);

        $this->assertNull(Downloader::getLatestVersion());
    }

    public function testShouldReturnNullIfRequestToGetLatestVersionReturnsInvalidXml()
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn('is not XML');

        $this->assertNull(Downloader::getLatestVersion());
    }

    public function testShouldReturnNullIfLatestVersionCannotBeFound()
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response-broken.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn($releasesDummyResponse);

        $this->assertNull(Downloader::getLatestVersion());
    }

    /**
     * @group integration
     */
    public function testShouldReturnValidLinkToSpecifiedVersion()
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $downloader->setVersion('2.53.1');

        $this->assertIsDownloadable($downloader->getFileUrl());
    }

    /**
     * @group integration
     */
    public function testShouldReadLatestVersionFromTheStorageUrl()
    {
        $latestVersion = Downloader::getLatestVersion();
        $this->assertInternalType('string', $latestVersion);
        $this->assertRegExp('/^\d+\.\d+\.\d+.*$/', $latestVersion);

        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $this->assertIsDownloadable($downloader->getFileUrl());
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
        $this->assertEquals('3.0.0-beta1', $downloader->getVersion());
    }

    public function testShouldAssembleTargetFilePath()
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $downloader->setVersion('3.33.6');

        $this->assertEquals(__DIR__ . '/Fixtures/selenium-server-standalone-3.33.6.jar', $downloader->getFilePath());
    }

    /**
     * @dataProvider provideVersions
     * @param string $version
     * @param string $expectedPath
     */
    public function testShouldAssembleUrlToDownload($version, $expectedPath)
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $downloader->setVersion($version);

        $this->assertEquals(
            'https://selenium-release.storage.googleapis.com' . $expectedPath,
            $downloader->getFileUrl()
        );
    }

    /**
     * @return array[]
     */
    public function provideVersions()
    {
        return [
            ['2.53.0', '/2.53/selenium-server-standalone-2.53.0.jar'],
            ['2.53.1', '/2.53/selenium-server-standalone-2.53.1.jar'],
            ['3.0.0-beta2', '/3.0-beta2/selenium-server-standalone-3.0.0-beta2.jar'],
            ['3.0.0-rc3', '/3.0-rc3/selenium-server-standalone-3.0.0-rc3.jar'],
            ['3.0.0', '/3.0/selenium-server-standalone-3.0.0.jar'],
        ];
    }

    /**
     * @dataProvider provideInvalidVersion
     * @param string $version
     */
    public function testShouldThrowExceptionIfInvalidVersionGiven($version)
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $downloader->setVersion($version);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid version (expected format is X.Y.Z)');
        $downloader->getFileUrl();
    }

    /**
     * @return array[]
     */
    public function provideInvalidVersion()
    {
        return [
            [' '],
            ['333'],
            ['1.2.3.4'],
            ['1.2'],
        ];
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

        $this->mockGetHeadersToReturnHeader('HTTP/1.0 200 OK');

        $this->assertEquals(9, $downloader->download());
        $this->assertFileExists($expectedFile);

        // Cleanup
        unlink($expectedFile);
    }

    public function testShouldThrowExceptionIfFileCannotBeDownloaded()
    {
        $downloader = $this->getMockBuilder(Downloader::class)
            ->setConstructorArgs([__DIR__ . '/Fixtures'])
            ->setMethods(['getFileUrl'])
            ->getMock();

        $downloader->expects($this->once())
            ->method('getFileUrl')
            ->willReturn(__DIR__ . '/Fixtures/dummy-file.jar');

        $downloader->setVersion('1.33.7');

        $this->mockGetHeadersToReturnHeader('HTTP/1.0 404 Not Found');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageRegExp('/Error downloading file "[^"]+" \(HTTP\/1\.0 404 Not Found\)/');

        $downloader->download();
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

        $this->mockGetHeadersToReturnHeader('HTTP/1.0 200 OK');

        $downloader->download();

        // Directory should now be crated
        $this->assertFileExists($expectedDirectory);

        // Cleanup
        unlink($downloader->getFilePath());
        rmdir($expectedDirectory);
    }

    /**
     * @param string $url
     */
    private function assertIsDownloadable($url)
    {
        $context = stream_context_create(['http' => ['method' => 'HEAD', 'ignore_errors' => true]]);
        $fd = fopen($url, 'rb', false, $context);
        $responseCode = $http_response_header[0];
        fclose($fd);

        $this->assertContains('200 OK', $responseCode);
    }

    /**
     * @param string $responseHeader
     */
    private function mockGetHeadersToReturnHeader($responseHeader)
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'get_headers');
        $fileGetContentsMock->expects($this->any())
            ->willReturn([0 => $responseHeader]);
    }
}
