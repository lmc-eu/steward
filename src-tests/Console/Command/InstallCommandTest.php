<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Selenium\Downloader;
use phpmock\phpunit\PHPMock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers Lmc\Steward\Console\Command\InstallCommand
 */
class InstallCommandTest extends \PHPUnit_Framework_TestCase
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
            ]
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

        $this->tester->execute(['command' => $this->command->getName()]);

        $this->assertContains(
            'Enter Selenium server version to install: [5.67.8]',
            $this->tester->getDisplay()
        );

        // Check latest version was downloaded
        $this->assertContains(
            'File URL: https://selenium-release.storage.googleapis.com/5.67/selenium-server-standalone-5.67.8.jar',
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldDownloadVersionEnteredByUser()
    {
        $this->command->setDownloader($this->getDownloadMock());
        $this->mockLatestVersionCheck();
        $this->mockUserInput("1.33.7\n");

        $this->tester->execute(['command' => $this->command->getName()]);

        // Check custom version was downloaded
        $this->assertContains(
            'File URL: https://selenium-release.storage.googleapis.com/1.33/selenium-server-standalone-1.33.7.jar',
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldPrintErrorIfDownloadFails()
    {
        $this->command->setDownloader($this->getDownloadMock($expectedFileSize = false));

        $this->tester->execute(['command' => $this->command->getName(), 'version' => '6.3.6']);

        $this->assertContains('Error downloading file :-', $this->tester->getDisplay());
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testShouldOutputOnlyFilePathInNonInteractiveModeAndDownloadVersionProvidedAsOption()
    {
        // Path to an existing file
        $filePath = __DIR__ . '/Fixtures/vendor/bin/selenium-server-standalone-6.33.6.jar';

        $this->command->setDownloader($this->getDownloadMock());
        $this->mockLatestVersionCheck(); // will provide 5.67.8 as an version

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
        $filePath = __DIR__ . '/Fixtures/vendor/bin/selenium-server-standalone-5.67.8.jar';

        $this->command->setDownloader($this->getDownloadMock());
        $this->mockLatestVersionCheck(); // will provide 5.67.8 as an version

        $this->tester->execute(
            ['command' => $this->command->getName()], // do not specify custom version
            ['interactive' => false]
        );

        $this->assertEquals(realpath($filePath) . "\n", $this->tester->getDisplay());
        $this->assertContains('5.67.8', $this->tester->getDisplay());
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldNotDownloadTheFileAgainIfAlreadyExists()
    {
        $this->command->setDownloader(new Downloader(__DIR__ . '/Fixtures/vendor/bin'));
        $this->mockUserInput("\n");

        $this->tester->execute(['command' => $this->command->getName(), 'version' => '5.67.8']);

        $this->assertContains(
            'File "selenium-server-standalone-5.67.8.jar" already exists',
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldNotDownloadTheFileAgainIfAlreadyExistsOutputOnlyFilePathInNonInteractiveMode()
    {
        $this->command->setDownloader(new Downloader(__DIR__ . '/Fixtures/vendor/bin'));
        $this->mockUserInput("\n");

        $this->tester->execute(
            ['command' => $this->command->getName(), 'version' => '5.67.8'],
            ['interactive' => false]
        );

        $this->assertEquals(
            realpath(__DIR__ . '/Fixtures/vendor/bin/selenium-server-standalone-5.67.8.jar') . "\n",
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
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
     * Make latest version check to return version 5.67.8
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
        fputs($stream, $input);
        rewind($stream);

        $dialog = $this->command->getHelper('question');
        $dialog->setInputStream($stream);
    }
}
