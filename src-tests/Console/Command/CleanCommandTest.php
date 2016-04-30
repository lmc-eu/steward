<?php

namespace Lmc\Steward\Console\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @covers Lmc\Steward\Console\Command\CleanCommand
 */
class CleanCommandTest extends \PHPUnit_Framework_TestCase
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

    public function testShouldShowErrorIfLogsDirectoryCannotBeFound()
    {
        $this->setExpectedException(
            \RuntimeException::class,
            'Cannot clean logs directory "/not/accessible", make sure it is accessible.'
        );

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--logs-dir' => '/not/accessible',
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
