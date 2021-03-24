<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\Application;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Exception\CommandException;
use Lmc\Steward\Selenium\Downloader;
use Lmc\Steward\Selenium\Version;
use Lmc\Steward\Selenium\VersionResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers \Lmc\Steward\Console\Command\InstallCommand
 */
class InstallCommandTest extends TestCase
{
    /** @var InstallCommand */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    protected function setUp(): void
    {
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->add(new InstallCommand($dispatcher));

        /** @var InstallCommand $command */
        $command = $application->find('install');
        $this->command = $command;
        $this->tester = new CommandTester($this->command);
    }

    public function testShouldDownloadWithoutAskingForInputWhenVersionIsDefinedAsOption(): void
    {
        $this->command->setDownloader($this->getDownloadMock($expectedFileSize = 2 * 1024 * 1024));

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'version' => '6.3.6',
            ]
        );

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Downloading Selenium standalone server version 6.3.6', $output);
        $this->assertStringContainsString('Downloaded 2 MB, file saved successfully.', $this->tester->getDisplay());

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @requires function Symfony\Component\Console\Tester\CommandTester::setInputs
     */
    public function testShouldDownloadLatestVersionIfUserDoesNotEnterItsOwn(): void
    {
        $this->command->setDownloader($this->getDownloadMock());
        $this->command->setVersionResolver($this->createVersionResolverMock('2.34.5'));

        $this->tester->setInputs([PHP_EOL]);

        $this->tester->execute(
            ['command' => $this->command->getName()]
        );

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('Enter Selenium server version to install [2.34.5]:', $output);

        // Check latest version was downloaded
        $this->assertStringContainsString('Downloading Selenium standalone server version 2.34.5', $output);
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @requires function Symfony\Component\Console\Tester\CommandTester::setInputs
     */
    public function testShouldDownloadVersionEnteredByUser(): void
    {
        $this->command->setDownloader($this->getDownloadMock());
        $this->command->setVersionResolver($this->createVersionResolverMock('2.34.5'));

        $this->tester->setInputs(['1.33.7' . PHP_EOL]);

        $this->tester->execute(
            ['command' => $this->command->getName()],
        );

        // Check custom version was downloaded
        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Downloading Selenium standalone server version 1.33.7', $output);
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @requires function Symfony\Component\Console\Tester\CommandTester::setInputs
     */
    public function testShouldRequireVersionToBeEnteredIfLastVersionCheckFails(): void
    {
        $this->command->setDownloader($this->getDownloadMock());
        $this->command->setVersionResolver($this->createVersionResolverMock(null));

        $this->tester->setInputs([PHP_EOL, '6.6.6']);

        $this->tester->execute(
            ['command' => $this->command->getName()],
        );

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Enter Selenium server version to install:', $output);
        $this->assertStringContainsString(
            'Please provide version to download (latest version auto-detect failed)',
            $output
        );
        $this->assertStringContainsString('Downloading Selenium standalone server version 6.6.6', $output);
    }

    public function testShouldThrowAnExceptionInNonInteractiveModeIfLastVersionCheckFailsAndNoVersionWasProvided(): void
    {
        $this->command->setVersionResolver($this->createVersionResolverMock(null));

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Auto-detection of latest Selenium version failed - version must be provided');

        $this->tester->execute(
            ['command' => $this->command->getName()],
            ['interactive' => false, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE]
        );
    }

    public function testShouldOutputOnlyFilePathInNonInteractiveModeAndDownloadVersionProvidedAsOption(): void
    {
        // Path to an existing file
        $filePath = __DIR__ . '/Fixtures/vendor/bin/selenium-server-standalone-6.33.6.jar';

        $downloader = $this->createConfiguredMock(
            Downloader::class,
            [
                'download' => 333,
                'isAlreadyDownloaded' => false,
                'getFilePath' => $filePath,
            ]
        );
        $this->command->setDownloader($downloader);

        $this->tester->execute(
            ['command' => $this->command->getName(), 'version' => '6.33.6'], // specify 6.33.6 as custom option
            ['interactive' => false]
        );

        $output = $this->tester->getDisplay();
        $this->assertEquals(realpath($filePath) . PHP_EOL, $output);
        $this->assertStringContainsString('6.33.6', $output);
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testShouldOutputOnlyFilePathInNonInteractiveModeAndDownloadLatestVersionIfNoneProvided(): void
    {
        // Path to an existing file
        $filePath = __DIR__ . '/Fixtures/vendor/bin/selenium-server-standalone-2.34.5.jar';

        $this->command->setDownloader($this->getDownloadMock());
        $this->command->setVersionResolver($this->createVersionResolverMock('2.34.5'));

        $this->tester->execute(
            ['command' => $this->command->getName()], // do not specify custom version
            ['interactive' => false]
        );

        $this->assertEquals(realpath($filePath) . PHP_EOL, $this->tester->getDisplay());
        $this->assertStringContainsString('2.34.5', $this->tester->getDisplay());
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @requires function Symfony\Component\Console\Tester\CommandTester::setInputs
     */
    public function testShouldNotDownloadTheFileAgainIfAlreadyExists(): void
    {
        $this->command->setDownloader(
            new Downloader(__DIR__ . '/Fixtures/vendor/bin', Version::createFromString('2.34.5'))
        );

        $this->tester->setInputs([PHP_EOL]);

        $this->tester->execute(['command' => $this->command->getName(), 'version' => '2.34.5']);

        $this->assertStringContainsString(
            'File "selenium-server-standalone-2.34.5.jar" already exists',
            $this->tester->getDisplay()
        );
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    /**
     * @requires function Symfony\Component\Console\Tester\CommandTester::setInputs
     */
    public function testShouldNotDownloadTheFileAgainIfAlreadyExistsOutputOnlyFilePathInNonInteractiveMode(): void
    {
        $this->command->setDownloader(
            new Downloader(__DIR__ . '/Fixtures/vendor/bin', Version::createFromString('2.34.5'))
        );

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

    public function testShouldDispatchEventsOnExecute(): void
    {
        $dispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();

        $dispatcherMock->expects($this->at(0))
            ->method('dispatch')
            ->with($this->isInstanceOf(BasicConsoleEvent::class), $this->equalTo(CommandEvents::CONFIGURE));

        $dispatcherMock->expects($this->at(1))
            ->method('dispatch')
            ->with($this->isInstanceOf(ExtendedConsoleEvent::class), $this->equalTo(CommandEvents::PRE_INITIALIZE));

        $application = new Application();
        $application->add(new InstallCommand($dispatcherMock));
        /** @var InstallCommand $command */
        $command = $application->find('install');
        $command->setDownloader($this->getDownloadMock());

        (new CommandTester($command))->execute(['command' => $command->getName(), 'version' => '6.3.6']);
    }

    /**
     * Get Downloader mock mocking isAlreadyDownloaded and download method to act like file is being downloaded
     *
     * @param int|bool $expectedFileSize
     * @return Downloader&MockObject
     */
    protected function getDownloadMock($expectedFileSize = 123): MockObject
    {
        $downloaderMock = $this->getMockBuilder(Downloader::class)
            ->setConstructorArgs([__DIR__ . '/Fixtures/vendor/bin', Version::createFromString('2.34.5')])
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
     * @return VersionResolver&MockObject
     */
    private function createVersionResolverMock(?string $latestVersion): MockObject
    {
        return $this->createConfiguredMock(
            VersionResolver::class,
            [
                'getLatestVersion' => $latestVersion === null ? null : Version::createFromString($latestVersion),
            ]
        );
    }
}
