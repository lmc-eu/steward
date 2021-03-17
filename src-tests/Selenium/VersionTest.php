<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    /**
     * @dataProvider provideVersion
     */
    public function testShouldResolveVersion(
        string $version,
        string $expectedMajor,
        string $expectedMinor,
        string $expectedBugfix,
        string $expectedDev
    ): void {
        $resolver = Version::createFromString($version);

        $this->assertSame($version, $resolver->toString());
        $this->assertSame($expectedMajor, $resolver->getMajor());
        $this->assertSame($expectedMinor, $resolver->getMinor());
        $this->assertSame($expectedBugfix, $resolver->getBugfix());
        $this->assertSame($expectedDev, $resolver->getDev());
    }

    /**
     * @return array[]
     */
    public function provideVersion(): array
    {
        return [
            'basic version' => ['1.33.7', '1', '33', '7', ''],
            'dev version' => ['1.2.3-rc', '1', '2', '3', 'rc'],
            'selenium 3 PI version' => ['3.141.59', '3', '141', '59', ''],
            'selenium 3 dev without hyphen' => ['3.0.0-beta2', '3', '0', '0', 'beta2'],
            'selenium 4 dev with hyphen' => ['4.0.0-beta-1', '4', '0', '0', 'beta-1'],
            'selenium 4 stable' => ['4.0.0', '4', '0', '0', ''],
        ];
    }

    /**
     * @dataProvider provideInvalidVersion
     */
    public function testShouldThrowExceptionIfInvalidVersionGiven(string $version): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid version (expected format is X.Y.Z)');

        Version::createFromString($version);
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
}
