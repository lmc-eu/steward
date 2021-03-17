<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Lmc\Steward\Selenium\VersionResolver
 */
class VersionResolverTest extends TestCase
{
    use PHPMock;

    public function testShouldGetAvailableVersions(): void
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->with('https://selenium-release.storage.googleapis.com')
            ->willReturn($releasesDummyResponse);

        $resolver = new VersionResolver();
        $availableVersions = $resolver->getAvailableVersions();

        $this->assertContainsOnlyInstancesOf(Version::class, $availableVersions);

        $this->assertSame(
            [
                '2.53.0',
                '2.53.1',
                '3.0.0-beta1',
                '3.0.0-beta2',
                '3.0.0-beta3',
                '3.0.0-beta4',
                '3.0.0',
                '3.0.1',
                '3.1.0',
                '3.2.0',
                '3.14.0',
                '3.141.0',
                '3.141.5',
                '3.141.59',
                '4.0.0-alpha-5',
                '4.0.0-alpha-6',
                '4.0.0-alpha-7',
                '4.0.0-beta-1',
                '4.0.0',
            ],
            array_map(
                static function (Version $version) {
                    return $version->toString();
                },
                $availableVersions
            )
        );
    }

    public function testShouldGetLatestVersion(): void
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->with('https://selenium-release.storage.googleapis.com')
            ->willReturn($releasesDummyResponse);

        $this->assertEquals('4.0.0', (new VersionResolver())->getLatestVersion()->toString());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfRequestToGetVersionFailed(): void
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn(false);

        $this->assertSame([], (new VersionResolver())->getAvailableVersions());
        $this->assertNull((new VersionResolver())->getLatestVersion());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfRequestToGetLatestVersionReturnsInvalidXml(): void
    {
        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn('is not XML');

        $this->assertSame([], (new VersionResolver())->getAvailableVersions());
        $this->assertNull((new VersionResolver())->getLatestVersion());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfLatestVersionCannotBeFound(): void
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response-missing.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn($releasesDummyResponse);

        $this->assertSame([], (new VersionResolver())->getAvailableVersions());
        $this->assertNull((new VersionResolver())->getLatestVersion());
    }

    public function testShouldNotIncludeInvalidVersionsInAvailableVersions(): void
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response-invalid-version.xml');

        $fileGetContentsMock = $this->getFunctionMock(__NAMESPACE__, 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn($releasesDummyResponse);

        $this->assertEquals(
            [Version::createFromString('3.1.0'), Version::createFromString('3.10.0')],
            (new VersionResolver())->getAvailableVersions()
        );
    }

    /**
     * @group integration
     */
    public function testShouldResolveActualAvailableVersion(): void
    {
        $availableVersions = (new VersionResolver())->getAvailableVersions();

        $this->assertGreaterThanOrEqual(59, count($availableVersions));
    }

    /**
     * @group integration
     */
    public function testShouldResolveActualLatestVersion(): void
    {
        $latestVersion = (new VersionResolver())->getLatestVersion();
        $this->assertRegExp('/^\d+\.\d+\.\d+.*$/', $latestVersion->toString());

        $this->assertGreaterThanOrEqual((int) $latestVersion->getMajor(), 4);
    }
}
