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

    public function testShouldGetLatestVersion(): void
    {
        $fileGetContentsMock = $this->createConfiguredMock(
            FileGetContentsWrapper::class,
            ['fileGetContents' => file_get_contents(__DIR__ . '/Fixtures/releases-latest.json')]
        );

        $this->resolver->setFileGetContentsWrapper($fileGetContentsMock);

        $this->assertEquals('3.141.59', $this->resolver->getLatestVersion()->toString());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfRequestToGetLatestVersionReturnsInvalidJson(): void
    {
        $fileGetContentsMock = $this->createConfiguredMock(
            FileGetContentsWrapper::class,
            ['fileGetContents' => 'this is not JSON']
        );

        $this->resolver->setFileGetContentsWrapper($fileGetContentsMock);

        $this->assertNull($this->resolver->getLatestVersion());
    }

    public function testShouldReturnEmptyArrayOfAvailableVersionsIfLatestVersionCannotBeFound(): void
    {
        $fileGetContentsMock = $this->createConfiguredMock(
            FileGetContentsWrapper::class,
            ['fileGetContents' => file_get_contents(__DIR__ . '/Fixtures/releases-response-missing.json')]
        );

        $this->resolver->setFileGetContentsWrapper($fileGetContentsMock);

        $this->assertNull($this->resolver->getLatestVersion());
    }

    /**
     * @group integration
     */
    public function testShouldResolveActualLatestVersion(): void
    {
        $latestVersion = (new VersionResolver())->getLatestVersion();
        $this->assertRegExp('/^\d+\.\d+\.\d+.*$/', $latestVersion->toString());

        $this->assertGreaterThanOrEqual(4, (int) $latestVersion->getMajor());
    }
}
