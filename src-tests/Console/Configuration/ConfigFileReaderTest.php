<?php

namespace Lmc\Steward\Console\Configuration;

use Assert\InvalidArgumentException;

/**
 * @covers \Lmc\Steward\Console\Configuration\ConfigFileReader
 */
class ConfigFileReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider providePathToConfigFile
     * @param string|null $customPath
     * @param string $subdir
     * @param string $expectedPath
     */
    public function testShouldResolvePathToConfigFile($customPath, $subdir, $expectedPath)
    {
        $fileReader = new ConfigFileReader();

        $resolvedPath = $fileReader->resolvePathToConfigFile($customPath, __DIR__ . '/Fixtures/' . $subdir);

        $this->assertStringEndsWith($expectedPath, $resolvedPath);
    }

    /**
     * @return array[]
     */
    public function providePathToConfigFile()
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

    public function testShouldReadYamlConfigFile()
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

    public function testShouldReturnEmptyArrayIfFileIsEmpty()
    {
        $fileReader = new ConfigFileReader();

        $this->assertSame([], $fileReader->readConfigFile(__DIR__ . '/Fixtures/empty.yml'));
    }

    public function testShouldThrowExceptionIfFileToReadNotExists()
    {
        $fileReader = new ConfigFileReader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File "/not/existing.yml" was expected to exist.');
        $fileReader->readConfigFile('/not/existing.yml');
    }
}
