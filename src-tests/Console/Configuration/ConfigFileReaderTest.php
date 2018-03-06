<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Configuration;

use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Lmc\Steward\Console\Configuration\ConfigFileReader
 */
class ConfigFileReaderTest extends TestCase
{
    /**
     * @dataProvider providePathToConfigFile
     */
    public function testShouldResolvePathToConfigFile(?string $customPath, string $subdir, string $expectedPath): void
    {
        $fileReader = new ConfigFileReader();

        $resolvedPath = $fileReader->resolvePathToConfigFile($customPath, __DIR__ . '/Fixtures/' . $subdir);

        $this->assertStringEndsWith($expectedPath, $resolvedPath);
    }

    /**
     * @return array[]
     */
    public function providePathToConfigFile(): array
    {
        $subdirWithDistConfig = 'with-only-dist';
        $subdirWithLocalConfig = 'with-only-local';
        $subdirWithBothDistAndLocalConfig = 'with-both-dist-and-local';
        $subdirWithoutConfig = 'without-config';

        return [
            'only dist config file => use it' => [null, $subdirWithDistConfig, 'steward.yml.dist'],
            'only local config => use it' => [null, $subdirWithLocalConfig, 'steward.yml'],
            'both dist and local config => use local' => [null, $subdirWithBothDistAndLocalConfig, 'steward.yml'],
            'without any config => empty path' => [null, $subdirWithoutConfig, ''],
            'custom path => override default config files' => [
                __DIR__ . '/Fixtures/empty.yml',
                $subdirWithBothDistAndLocalConfig,
                'empty.yml',
            ],
        ];
    }

    public function testShouldReadYamlConfigFile(): void
    {
        $fileReader = new ConfigFileReader();

        $this->assertSame(
            [
                'foo' => 'bar',
                'baz' => ['ban', 'bat'],
            ],
            $fileReader->readConfigFile(__DIR__ . '/Fixtures/dummy.yml')
        );
    }

    public function testShouldReturnEmptyArrayIfFileIsEmpty(): void
    {
        $fileReader = new ConfigFileReader();

        $this->assertSame([], $fileReader->readConfigFile(__DIR__ . '/Fixtures/empty.yml'));
    }

    public function testShouldThrowExceptionIfFileToReadNotExists(): void
    {
        $fileReader = new ConfigFileReader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File "/not/existing.yml" was expected to exist.');
        $fileReader->readConfigFile('/not/existing.yml');
    }
}
