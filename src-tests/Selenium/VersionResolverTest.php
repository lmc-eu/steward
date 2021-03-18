<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

use Lmc\Steward\Utils\FileGetContentsWrapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Lmc\Steward\Selenium\VersionResolver
 * @covers \Lmc\Steward\Utils\FileGetContentsWrapper
 */
class VersionResolverTest extends TestCase
{
    /** @var VersionResolver */
    protected $resolver;

    protected function setUp(): void
    {
        $this->resolver = new VersionResolver();
    }

    public function testShouldGetAvailableVersions(): void
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response.xml');
        $fileGetContentsMock = $this->createConfiguredMock(
            FileGetContentsWrapper::class,
            ['fileGetContents' => $releasesDummyResponse]
        );

        $this->resolver->setFileGetContentsWrapper($fileGetContentsMock);
        $availableVersions = $this->resolver->getAvailableVersions();

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
        $fileGetContentsMock = $this->createConfiguredMock(
            FileGetContentsWrapper::class,
            ['fileGetContents' => file_get_contents(__DIR__ . '/Fixtures/releases-response.xml')]
        );

        $this->resolver->setFileGetContentsWrapper($fileGetContentsMock);

        $this->assertEquals('4.0.0', $this->resolver->getLatestVersion()->toString());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfRequestToGetVersionFailed(): void
    {
        $fileGetContentsMock = $this->createConfiguredMock(
            FileGetContentsWrapper::class,
            ['fileGetContents' => false]
        );

        $this->resolver->setFileGetContentsWrapper($fileGetContentsMock);

        $this->assertSame([], $this->resolver->getAvailableVersions());
        $this->assertNull($this->resolver->getLatestVersion());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfRequestToGetLatestVersionReturnsInvalidXml(): void
    {
        $fileGetContentsMock = $this->createConfiguredMock(
            FileGetContentsWrapper::class,
            ['fileGetContents' => 'this is not XM']
        );

        $this->resolver->setFileGetContentsWrapper($fileGetContentsMock);

        $this->assertSame([], $this->resolver->getAvailableVersions());
        $this->assertNull($this->resolver->getLatestVersion());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfLatestVersionCannotBeFound(): void
    {
        $fileGetContentsMock = $this->createConfiguredMock(
            FileGetContentsWrapper::class,
            ['fileGetContents' => file_get_contents(__DIR__ . '/Fixtures/releases-response-missing.xml')]
        );

        $this->resolver->setFileGetContentsWrapper($fileGetContentsMock);

        $this->assertSame([], $this->resolver->getAvailableVersions());
        $this->assertNull($this->resolver->getLatestVersion());
    }

    public function testShouldNotIncludeInvalidVersionsInAvailableVersions(): void
    {
        $fileGetContentsMock = $this->createConfiguredMock(
            FileGetContentsWrapper::class,
            ['fileGetContents' => file_get_contents(__DIR__ . '/Fixtures/releases-response-invalid-version.xml')]
        );

        $this->resolver->setFileGetContentsWrapper($fileGetContentsMock);

        $this->assertEquals(
            [Version::createFromString('3.1.0'), Version::createFromString('3.10.0')],
            $this->resolver->getAvailableVersions()
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
