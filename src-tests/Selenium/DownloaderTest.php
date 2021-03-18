<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

use Lmc\Steward\AssertDownloadableTrait;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Lmc\Steward\Selenium\Downloader
 */
class DownloaderTest extends TestCase
{
    use AssertDownloadableTrait;
    use PHPMock;

    /**
     * @group integration
     */
    public function testShouldDownloadActualLatestVersionFromTheStorageUrl(): void
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures', (new VersionResolver())->getLatestVersion());
        $this->assertIsDownloadable($downloader->getFileUrl());
    }

    /**
     * @group integration
     */
    public function testShouldReadGivenVersionOfSelenium3FromTheStorageUrl(): void
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures', Version::createFromString('3.141.59'));

        $this->assertIsDownloadable($downloader->getFileUrl());
    }

    public function testShouldAssembleTargetFilePath(): void
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures', Version::createFromString('3.33.6'));

        $this->assertEquals(__DIR__ . '/Fixtures/selenium-server-standalone-3.33.6.jar', $downloader->getFilePath());
    }

    /**
     * @dataProvider provideVersions
     */
    public function testShouldAssembleUrlToDownload(string $version, string $expectedPath): void
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures', Version::createFromString($version));

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
            ['4.0.0-beta-1', '/4.0-beta-1/selenium-server-4.0.0-beta-1.jar'],
            ['4.0.0', '/4.0/selenium-server-4.0.0.jar'],
        ];
    }

    public function testShouldCheckIfFileWasAlreadyDownloaded(): void
    {
        $downloader = new Downloader(__DIR__ . '/Fixtures', Version::createFromString('2.45.0'));
        $this->assertTrue($downloader->isAlreadyDownloaded());

        $downloader = new Downloader(__DIR__ . '/Fixtures', Version::createFromString('2.66.6'));
        $this->assertFalse($downloader->isAlreadyDownloaded());
    }

    public function testShouldStoreDownloadedFileToExpectedLocation(): void
    {
        // Mock getFileUrl() method to return URL to fixtures on filesystem
        /** @var Downloader|MockObject $downloader */
        $downloader = $this->getMockBuilder(Downloader::class)
            ->setConstructorArgs([__DIR__ . '/Fixtures', Version::createFromString('1.33.7')])
            ->setMethods(['getFileUrl'])
            ->getMock();

        $downloader->expects($this->once())
            ->method('getFileUrl')
            ->willReturn(__DIR__ . '/Fixtures/dummy-file.jar');

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
            ->setConstructorArgs([__DIR__ . '/Fixtures', Version::createFromString('1.33.7')])
            ->setMethods(['getFileUrl'])
            ->getMock();

        $downloader->expects($this->once())
            ->method('getFileUrl')
            ->willReturn(__DIR__ . '/Fixtures/dummy-file.jar');

        $this->mockGetHeadersToReturnHeader('HTTP/1.0 404 Not Found');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Error downloading file "[^"]+" \(HTTP\/1\.0 404 Not Found\)/');

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
            ->setConstructorArgs([$expectedDirectory, Version::createFromString('1.33.7')])
            ->setMethods(['getFileUrl'])
            ->getMock();

        $downloader->expects($this->once())
            ->method('getFileUrl')
            ->willReturn(__DIR__ . '/Fixtures/dummy-file.jar');

        $this->mockGetHeadersToReturnHeader('HTTP/1.0 200 OK');

        $downloader->download();

        // Directory should now be crated
        $this->assertFileExists($expectedDirectory);

        // Cleanup
        unlink($downloader->getFilePath());
        rmdir($expectedDirectory);
    }

    private function mockGetHeadersToReturnHeader(string $responseHeader): void
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'get_headers');
        $fileGetContentsMock->expects($this->any())
            ->willReturn([0 => $responseHeader]);
    }
}
