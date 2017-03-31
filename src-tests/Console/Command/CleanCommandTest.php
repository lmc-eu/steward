<?php

namespace Lmc\Steward\Console\Command;

use Assert\InvalidArgumentException;
use Lmc\Steward\Console\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @covers Lmc\Steward\Console\Command\CleanCommand
 */
class CleanCommandTest extends TestCase
{
    /** @var CleanCommand */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    protected function setUp()
    {
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->add(new CleanCommand($dispatcher));

        $this->command = $application->find('clean');
        $this->tester = new CommandTester($this->command);
    }

    public function testShouldShowErrorIfLogsDirectoryIsNotDefaultAndCannotBeFound()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path to directory with logs "/custom/not/accessible/path" does not exist');

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--logs-dir' => '/custom/not/accessible/path',
            ]
        );
    }

    public function testShouldCreateLogsDirectoryIfDefaultPathIsUsed()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Filesystem $filesystemMock */
        $filesystemMock = $this->getMockBuilder(Filesystem::class)
            ->setMethods(['exists', 'mkdir'])
            ->getMock();

        // Make default logs directory appear as not existing
        $filesystemMock->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        // The directory should be created then
        $filesystemMock->expects($this->once())
            ->method('mkdir');

        $this->command->setFilesystem($filesystemMock);

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
            ]
        );
    }

    public function testShouldCleanLogsDirectory()
    {
        $dir = __DIR__ . '/Fixtures/logs/';
        $fs = new Filesystem();
        $fs->touch([$dir . 'foo.png', $dir . 'bar.html', $dir . 'baz.xml', $dir . 'results.xml']);

        $filesCountBefore = (new Finder())->files()->in($dir)->count();

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--logs-dir' => $dir,
            ]
        );

        $filesCountAfter = (new Finder())->files()->in($dir)->count();
        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertSame($filesCountAfter, $filesCountBefore - 3);
        $this->assertFileNotExists($dir . 'foo.png');
        $this->assertFileNotExists($dir . 'foo.bar');
        $this->assertFileNotExists($dir . 'foo.html');
        $this->assertFileExists($dir . 'results.xml');
        $this->assertEmpty($output);
    }
}
