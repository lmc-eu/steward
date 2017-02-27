<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Selenium\Downloader;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
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
        $this->command->setDownloader($this->getDownloadMock($expectedFileSize = 123));

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'version' => '6.3.6',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE] // to get the file URL output
        );

        $this->assertContains(
            'File URL: https://selenium-release.storage.googleapis.com/6.3/selenium-server-standalone-6.3.6.jar',
            $this->tester->getDisplay()
        );
        $this->assertContains('Downloaded 123 bytes, file saved successfully.', $this->tester->getDisplay());

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldDownloadLatestVersionIfUserDoesNotEnterItsOwn()
    {
        $this->command->setDownloader($this->getDownloadMock());
        $this->mockLatestVersionCheck();
        $this->mockUserInput("\n"); // just press enter when asking for version to install to confirm default

        $this->tester->execute(
            ['command' => $this->command->getName()],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE] // to get the file URL output
        );

        $this->assertContains('Enter Selenium server version to install: [2.34.5]', $this->tester->getDisplay());

        // Check latest version was downloaded
        $this->assertContains(
            'File URL: https://selenium-release.storage.googleapis.com/2.34/selenium-server-standalone-2.34.5.jar',
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldDownloadVersionEnteredByUser()
    {
        $this->command->setDownloader($this->getDownloadMock());
        $this->mockLatestVersionCheck();
        $this->mockUserInput("1.33.7\n");

        $this->tester->execute(
            ['command' => $this->command->getName()],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE] // to get the file URL output
        );

        // Check custom version was downloaded
        $this->assertContains(
            'File URL: https://selenium-release.storage.googleapis.com/1.33/selenium-server-standalone-1.33.7.jar',
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldRequireVersionToBeEnteredIfLastVersionCheckFails()
    {
        $this->command->setDownloader($this->getDownloadMock());
        $fileGetContentsMock = $this->getFunctionMock('Lmc\Steward\Selenium', 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn(false);

        $this->mockUserInput("\n1.33.7");

        $this->tester->execute(
            ['command' => $this->command->getName()],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE] // to get the file URL output
        );

        $this->assertContains(
            'Enter Selenium server version to install: ',
            $this->tester->getDisplay()
        );
        $this->assertContains(
            ' Please provide version to download (latest version auto-detect failed)',
            $this->tester->getDisplay()
        );
    }

    public function testShouldPrintErrorIfDownloadFails()
    {
        $this->command->setDownloader($this->getDownloadMock($expectedFileSize = false));

        $this->tester->execute(['command' => $this->command->getName(), 'version' => '6.3.6']);

        $this->assertContains('Error downloading file :-(', $this->tester->getDisplay());
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testShouldExitInNonInteractiveModeIfLastVersionCheckFailsAndNoVersionWasProvided()
    {
        $downloaderMock = $this->getMockBuilder(Downloader::class)
            ->setConstructorArgs([__DIR__ . '/Fixtures/vendor/bin'])
            ->getMock();

        $this->command->setDownloader($downloaderMock);
        $fileGetContentsMock = $this->getFunctionMock('Lmc\Steward\Selenium', 'file_get_contents');
        $fileGetContentsMock->expects($this->any())
            ->willReturn(false);

        $this->tester->execute(
            ['command' => $this->command->getName()],
            ['interactive' => false, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE]
        );

        $this->assertEquals(
            "Please provide version to download (latest version auto-detect failed)\n",
            $this->tester->getDisplay()
        );

        $this->assertSame(1, $this->tester->getStatusCode());
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

        $this->assertEquals(realpath($filePath) . "\n", $this->tester->getDisplay());
        $this->assertContains('6.33.6', $this->tester->getDisplay());
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

        $this->assertEquals(realpath($filePath) . "\n", $this->tester->getDisplay());
        $this->assertContains('2.34.5', $this->tester->getDisplay());
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldNotDownloadTheFileAgainIfAlreadyExists()
    {
        $this->command->setDownloader(new Downloader(__DIR__ . '/Fixtures/vendor/bin'));
        $this->mockUserInput("\n");

        $this->tester->execute(['command' => $this->command->getName(), 'version' => '2.34.5']);

        $this->assertContains(
            'File "selenium-server-standalone-2.34.5.jar" already exists',
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldNotDownloadTheFileAgainIfAlreadyExistsOutputOnlyFilePathInNonInteractiveMode()
    {
        $this->command->setDownloader(new Downloader(__DIR__ . '/Fixtures/vendor/bin'));
        $this->mockUserInput("\n");

        $this->tester->execute(
            ['command' => $this->command->getName(), 'version' => '2.34.5'],
            ['interactive' => false]
        );

        $this->assertEquals(
            realpath(__DIR__ . '/Fixtures/vendor/bin/selenium-server-standalone-2.34.5.jar') . "\n",
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldDispatchConfigureEvent()
    {
        $dispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();

        $dispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(CommandEvents::CONFIGURE), $this->isInstanceOf(BasicConsoleEvent::class));

        $application = new Application();
        $application->add(new InstallCommand($dispatcherMock));
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

    /**
     * @param string $input Input string to be streamed
     * @return resource
     */
    protected function mockUserInput($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fwrite($stream, $input);
        rewind($stream);

        $dialog = $this->command->getHelper('question');
        $dialog->setInputStream($stream);
    }
}
