<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\Application;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Selenium\Downloader;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers Lmc\Steward\Console\Command\InstallCommand
 */
class InstallCommandTest extends TestCase
{
    use PHPMock;

    /** @var InstallCommand */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    protected function setUp()
    {
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->add(new InstallCommand($dispatcher));

        $this->command = $application->find('install');
        $this->tester = new CommandTester($this->command);
    }

    public function testShouldDownloadWithoutAskingForInputWhenVersionIsDefinedAsOption()
    {
        $this->command->setDownloader($this->getDownloadMock($expectedFileSize = 2 * 1024 * 1024));

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'version' => '6.3.6',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE] // to get the file URL output
        );

        $output = $this->tester->getDisplay();
        $this->assertContains('Downloading Selenium standalone server version 6.3.6...', $output);
        $this->assertContains(
            'Download URL: https://selenium-release.storage.googleapis.com/6.3/selenium-server-standalone-6.3.6.jar',
            $output
        );
        $this->assertContains('Downloaded 2 MB, file saved successfully.', $this->tester->getDisplay());

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @requires function Symfony\Component\Console\Tester\CommandTester::setInputs
     */
    public function testShouldDownloadLatestVersionIfUserDoesNotEnterItsOwn()
    {
        $this->command->setDownloader($this->getDownloadMock());
        $this->mockLatestVersionCheck();

        $this->tester->setInputs([PHP_EOL]);

        $this->tester->execute(
            ['command' => $this->command->getName()],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE] // to get the file URL output
        );

        $output = $this->tester->getDisplay();

        $this->assertContains('Enter Selenium server version to install [2.34.5]:', $output);

        // Check latest version was downloaded
        $this->assertContains('Downloading Selenium standalone server version 2.34.5...', $output);
        $this->assertContains(
            'Download URL: https://selenium-release.storage.googleapis.com/2.34/selenium-server-standalone-2.34.5.jar',
            $output
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @requires function Symfony\Component\Console\Tester\CommandTester::setInputs
     */
    public function testShouldDownloadVersionEnteredByUser()
    {
        $this->command->setDownloader($this->getDownloadMock());
        $this->mockLatestVersionCheck();

        $this->tester->setInputs(['1.33.7' . PHP_EOL]);

        $this->tester->execute(
            ['command' => $this->command->getName()],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE] // to get the file URL output
        );

        // Check custom version was downloaded
        $output = $this->tester->getDisplay();
        $this->assertContains('Downloading Selenium standalone server version 1.33.7...', $output);
        $this->assertContains(
            'Download URL: https://selenium-release.storage.googleapis.com/1.33/selenium-server-standalone-1.33.7.jar',
            $output
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @requires function Symfony\Component\Console\Tester\CommandTester::setInputs
     */
    public function testShouldRequireVersionToBeEnteredIfLastVersionCheckFails()
    {
        $this->command->setDownloader($this->getDownloadMock());
        $fileGetContentsMock = $this->getFunctionMock('Lmc\Steward\Selenium', 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn(false);

        $this->tester->setInputs([PHP_EOL, '6.6.6']);

        $this->tester->execute(
            ['command' => $this->command->getName()],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE] // to get the file URL output
        );

        $output = $this->tester->getDisplay();
        $this->assertContains('Enter Selenium server version to install:', $output);
        $this->assertContains('Please provide version to download (latest version auto-detect failed)', $output);
        $this->assertContains('Downloading Selenium standalone server version 6.6.6...', $output);
    }

    public function testShouldPrintErrorIfDownloadFails()
    {
        $this->command->setDownloader($this->getDownloadMock($expectedFileSize = false));

        $this->tester->execute(['command' => $this->command->getName(), 'version' => '6.3.6']);

        $this->assertContains('Error downloading file :-(', $this->tester->getDisplay());
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testShouldThrowAnExceptionInNonInteractiveModeIfLastVersionCheckFailsAndNoVersionWasProvided()
    {
        $downloaderMock = $this->getMockBuilder(Downloader::class)
            ->setConstructorArgs([__DIR__ . '/Fixtures/vendor/bin'])
            ->getMock();

        $this->command->setDownloader($downloaderMock);
        $fileGetContentsMock = $this->getFunctionMock('Lmc\Steward\Selenium', 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Auto-detection of latest Selenium version failed - version must be provided');

        $this->tester->execute(
            ['command' => $this->command->getName()],
            ['interactive' => false, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE]
        );
    }

    public function testShouldOutputOnlyFilePathInNonInteractiveModeAndDownloadVersionProvidedAsOption()
    {
        // Path to an existing file
        $filePath = __DIR__ . '/Fixtures/vendor/bin/selenium-server-standalone-6.33.6.jar';

        $this->command->setDownloader($this->getDownloadMock());
        $this->mockLatestVersionCheck(); // will provide 2.34.5 as an version

        $this->tester->execute(
            ['command' => $this->command->getName(), 'version' => '6.33.6'], // specify 6.33.6 as custom option
            ['interactive' => false]
        );

        $output = $this->tester->getDisplay();
        $this->assertEquals(realpath($filePath) . PHP_EOL, $output);
        $this->assertContains('6.33.6', $output);
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldOutputOnlyFilePathInNonInteractiveModeAndDownloadLatestVersionIfNoneProvided()
    {
        // Path to an existing file
        $filePath = __DIR__ . '/Fixtures/vendor/bin/selenium-server-standalone-2.34.5.jar';

        $this->command->setDownloader($this->getDownloadMock());
        $this->mockLatestVersionCheck(); // will provide 2.34.5 as an version

        $this->tester->execute(
            ['command' => $this->command->getName()], // do not specify custom version
            ['interactive' => false]
        );

        $this->assertEquals(realpath($filePath) . PHP_EOL, $this->tester->getDisplay());
        $this->assertContains('2.34.5', $this->tester->getDisplay());
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @requires function Symfony\Component\Console\Tester\CommandTester::setInputs
     */
    public function testShouldNotDownloadTheFileAgainIfAlreadyExists()
    {
        $this->command->setDownloader(new Downloader(__DIR__ . '/Fixtures/vendor/bin'));

        $this->tester->setInputs([PHP_EOL]);

        $this->tester->execute(['command' => $this->command->getName(), 'version' => '2.34.5']);

        $this->assertContains(
            'File "selenium-server-standalone-2.34.5.jar" already exists',
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @requires function Symfony\Component\Console\Tester\CommandTester::setInputs
     */
    public function testShouldNotDownloadTheFileAgainIfAlreadyExistsOutputOnlyFilePathInNonInteractiveMode()
    {
        $this->command->setDownloader(new Downloader(__DIR__ . '/Fixtures/vendor/bin'));

        $this->tester->setInputs([PHP_EOL]);

        $this->tester->execute(
            ['command' => $this->command->getName(), 'version' => '2.34.5'],
            ['interactive' => false]
        );

        $this->assertEquals(
            realpath(__DIR__ . '/Fixtures/vendor/bin/selenium-server-standalone-2.34.5.jar') . PHP_EOL,
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldDispatchEventsOnExecute()
    {
        $dispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();

        $dispatcherMock->expects($this->at(0))
            ->method('dispatch')
            ->with($this->equalTo(CommandEvents::CONFIGURE), $this->isInstanceOf(BasicConsoleEvent::class));

        $dispatcherMock->expects($this->at(1))
            ->method('dispatch')
            ->with($this->equalTo(CommandEvents::PRE_INITIALIZE), $this->isInstanceOf(ExtendedConsoleEvent::class));

        $application = new Application();
        $application->add(new InstallCommand($dispatcherMock));
        /** @var InstallCommand $command */
        $command = $application->find('install');
        $command->setDownloader($this->getDownloadMock());

        (new CommandTester($command))->execute(['command' => $command->getName(), 'version' => '6.3.6']);
    }

    /**
     * Get Downloader mock mocking isAlreadyDownloaded and download method to act like file is being downloaded
     * @param int|bool $expectedFileSize
     * @return Downloader|\PHPUnit_Framework_MockObject_MockObject $downloaderMock
     */
    protected function getDownloadMock($expectedFileSize = 123)
    {
        $downloaderMock = $this->getMockBuilder(Downloader::class)
            ->setConstructorArgs([__DIR__ . '/Fixtures/vendor/bin'])
            ->setMethods(['isAlreadyDownloaded', 'download'])
            ->getMock();

        // Fake file exists detection to always return false
        $downloaderMock->expects($this->once())
            ->method('isAlreadyDownloaded')
            ->willReturn(false);
        // Mock file downloading
        $downloaderMock->expects($this->once())
            ->method('download')
            ->willReturn($expectedFileSize);

        return $downloaderMock;
    }

    /**
     * Make latest version check to return version 2.34.5
     */
    protected function mockLatestVersionCheck()
    {
        $releasesDummyResponse = file_get_contents(__DIR__ . '/Fixtures/releases-response-minimal.xml');
        $fileGetContentsMock = $this->getFunctionMock('Lmc\Steward\Selenium', 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn($releasesDummyResponse);
    }
}
