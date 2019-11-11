<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

use Assert\InvalidArgumentException;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DownloaderTest extends TestCase
{
    use PHPMock;

    public function testShouldGetAvailableVersions(): void
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->with('https://selenium-release.storage.googleapis.com')
            ->willReturn($releasesDummyResponse);

        $this->assertSame(
            [
                '2.43.0',
                '2.43.1',
                '2.53.0',
                '2.53.1',
                '3.0.0-beta1',
                '3.0.0-beta2',
                '3.0.0-beta3',
                '3.0.0-beta3', // intentional, it there twice in the actual realworld response...
                '3.0.0-beta4',
                '3.0.0',
                '3.0.1',
                '3.1.0',
                '3.2.0',
                '3.4.0',
                '3.9.0',
                '3.9.1',
                '3.10.0',
                '3.11.0',
                '4.0.0-alpha-1',
                '4.0.0-alpha-2',
                '4.0.0',
            ],
            Downloader::getAvailableVersions()
        );
    }

    public function testShouldGetLatestVersion(): void
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->with('https://selenium-release.storage.googleapis.com')
            ->willReturn($releasesDummyResponse);

        $this->assertEquals('4.0.0', Downloader::getLatestVersion());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfRequestToGetVersionFailed(): void
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn(false);

        $this->assertSame([], Downloader::getAvailableVersions());
        $this->assertNull(Downloader::getLatestVersion());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfRequestToGetLatestVersionReturnsInvalidXml(): void
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn('is not XML');

        $this->assertSame([], Downloader::getAvailableVersions());
        $this->assertNull(Downloader::getLatestVersion());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfLatestVersionCannotBeFound(): void
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response-missing.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn($releasesDummyResponse);

        $this->assertSame([], Downloader::getAvailableVersions());
        $this->assertNull(Downloader::getLatestVersion());
    }

    public function testShouldNotIncludeInvalidVersionsInAvailableVersions(): void
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response-invalid-version.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn($releasesDummyResponse);

        $this->assertSame(['3.1.0', '3.10.0'], Downloader::getAvailableVersions());
    }

    /**
     * @group integration
     */
    public function testShouldReadLatestVersionFromTheStorageUrl(): void
    {
        $latestVersion = Downloader::getLatestVersion();
        $this->assertInternalType('string', $latestVersion);
        $this->assertRegExp('/^\d+\.\d+\.\d+.*$/', $latestVersion);

        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $this->assertIsDownloadable($downloader->getFileUrl());
    }

    public function testShouldGetVersionSetBySetter(): void
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');

        $downloader->setVersion('1.33.7');
        $this->assertEquals('1.33.7', $downloader->getVersion());

        $downloader->setVersion('6.33.6');
        $this->assertEquals('6.33.6', $downloader->getVersion());
    }

    public function testShouldGetLatestVersionOfNoneVersionSpecified(): void
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->with('https://selenium-release.storage.googleapis.com')
            ->willReturn($releasesDummyResponse);

        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $this->assertEquals('4.0.0', $downloader->getVersion());
    }

    public function testShouldAssembleTargetFilePath(): void
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $downloader->setVersion('3.33.6');

        $this->assertEquals(__DIR__ . '/Fixtures/selenium-server-standalone-3.33.6.jar', $downloader->getFilePath());
    }

    /**
     * @dataProvider provideVersions
     */
    public function testShouldAssembleUrlToDownload(string $version, string $expectedPath): void
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $downloader->setVersion($version);

        $this->assertSame(
            'https://selenium-release.storage.googleapis.com' . $expectedPath,
            $downloader->getFileUrl()
        );
    }

    /**
     * @return array[]
     */
    public function provideVersions(): array
    {
        return [
            ['2.53.0', '/2.53/selenium-server-standalone-2.53.0.jar'],
            ['2.53.1', '/2.53/selenium-server-standalone-2.53.1.jar'],
            ['3.0.0-beta2', '/3.0-beta2/selenium-server-standalone-3.0.0-beta2.jar'],
            ['3.0.0', '/3.0/selenium-server-standalone-3.0.0.jar'],
            ['4.0.0-alpha-1', '/4.0/selenium-server-standalone-4.0.0-alpha-1.jar'],
            ['4.0.0', '/4.0/selenium-server-standalone-4.0.0.jar'],
        ];
    }

    /**
     * @dataProvider provideInvalidVersion
     */
    public function testShouldThrowExceptionIfInvalidVersionGiven(string $version): void
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
    public function provideInvalidVersion(): array
    {
        return [
            [' '],
            ['333'],
            ['1.2.3.4'],
            ['1.2'],
        ];
    }

    public function testShouldCheckIfFileWasAlreadyDownloaded(): void
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures');
        $downloader->setVersion('2.45.0');

        $this->assertTrue($downloader->isAlreadyDownloaded());

        $downloader->setVersion('2.66.6');
        $this->assertFalse($downloader->isAlreadyDownloaded());
    }

    public function testShouldStoreDownloadedFileToExpectedLocation(): void
    {
        // Mock getFileUrl() method to return URL to fixtures on filesystem
        /** @var Downloader|MockObject $downloader */
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

    public function testShouldThrowExceptionIfFileCannotBeDownloaded(): void
    {
        /** @var MockObject|Downloader $downloader */
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

    public function testShouldCreateTargetDirectoryIfNotExists(): void
    {
        $expectedDirectory = __DIR__ . '/Fixtures/not/existing/directory';
        $this->assertFileNotExists(
            $expectedDirectory,
            'Directory already exists, though it should be created only by the test'
        );

        /** @var Downloader|MockObject $downloader */
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

    private function assertIsDownloadable(string $url): void
    {
        $context = stream_context_create(['http' => ['method' => 'HEAD', 'ignore_errors' => true]]);
        $fd = fopen($url, 'rb', false, $context);
        $responseCode = $http_response_header[0];
        fclose($fd);

        $this->assertContains('200 OK', $responseCode);
    }

    private function mockGetHeadersToReturnHeader(string $responseHeader): void
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'get_headers');
        $fileGetContentsMock->expects($this->any())
            ->willReturn([0 => $responseHeader]);
    }
}
