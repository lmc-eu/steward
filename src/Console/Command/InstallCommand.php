<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Exception\CommandException;
use Lmc\Steward\Selenium\Downloader;
use Lmc\Steward\Selenium\Version;
use Lmc\Steward\Selenium\VersionResolver;
use OndraM\CiDetector\CiDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Install (download) Selenium standalone server.
 */
class InstallCommand extends Command
{
    /** @var Downloader */
    protected $downloader;
    /** @var VersionResolver */
    protected $versionResolver;

    /**
     * Target directory to store the selenium server (relatively to STEWARD_BASE_DIR)
     *
     * @var string
     */
    protected $targetDir = '/vendor/bin';

    /**
     * @internal
     */
    public function setDownloader(Downloader $downloader): void
    {
        $this->downloader = $downloader;
    }

    /**
     * @internal
     */
    public function setVersionResolver(VersionResolver $versionResolver): void
    {
        $this->versionResolver = $versionResolver;
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('install')
            ->setDescription('Download Selenium standalone server jar file')
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'Specific Selenium version to install. If none provided, use the latest version.'
            );

        $this->getDispatcher()->dispatch(new BasicConsoleEvent($this), CommandEvents::CONFIGURE);
    }

    /**
     * In interactive or very verbose (-vv) mode provide more output, otherwise only output full path to selenium
     * server jar file (so it could be parsed and run).
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $verboseOutput = false;
        if ($input->isInteractive() || $output->isVerbose()) {
            $verboseOutput = true;
        }

        $versionInput = $input->getArgument('version'); // exact version could be specified as argument
        if (!$versionInput) {
            $versionInput = $this->askForVersion($input->isInteractive());
        }

        $version = Version::createFromString($versionInput);
        $downloader = $this->getDownloaderForVersion($version);
        $targetPath = realpath($downloader->getFilePath());

        if ($this->io->isVerbose()) {
            $this->io->note(sprintf('Download URL: %s', $downloader->getFileUrl()));
        }

        if ($downloader->isAlreadyDownloaded()) {
            if ($verboseOutput) {
                $this->io->note(
                    sprintf('File "%s" already exists - won\'t be downloaded again.', basename($targetPath))
                );

                $this->io->success('Path to file: ' . $targetPath);
                $this->printLinkToWiki();
            } else {
                $this->io->writeln($targetPath); // In non-verbose mode only output path to the file
            }

            return 0;
        }

        if ($verboseOutput) {
            $this->io->note(
                sprintf(
                    'Downloading Selenium standalone server version %s%s',
                    $version->toString(),
                    (!(new CiDetector())->isCiDetected() ? ' - just for you! ♥️' : '')
                )
            );

            $this->io->note('Downloading may take a while... (File size is over 20 MB.)');
        }

        $downloadedSize = $downloader->download();
        $downloadedFilePath = realpath($downloader->getFilePath());

        if ($verboseOutput) {
            $this->io->success(
                sprintf('Downloaded %d MB, file saved successfully.', round($downloadedSize / 1024 / 1024, 1))
            );
            $this->io->success('Path to file: ' . $downloadedFilePath);
            $this->printLinkToWiki();
        } else {
            $this->io->writeln($downloadedFilePath); // In non-verbose mode only output path to the file
        }

        return 0;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getDownloaderForVersion(Version $version): Downloader
    {
        if ($this->downloader === null) {
            $this->downloader = new Downloader(STEWARD_BASE_DIR . $this->targetDir, $version);
        }

        return $this->downloader;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getVersionResolver(): VersionResolver
    {
        if ($this->versionResolver === null) {
            $this->versionResolver = new VersionResolver();
        }

        return $this->versionResolver;
    }

    private function askForVersion(bool $isInteractiveInput): string
    {
        $questionText = 'Enter Selenium server version to install';
        $latestVersion = $this->getVersionResolver()->getLatestVersion();

        if ($latestVersion !== null) {
            return $this->io->ask($questionText, $latestVersion->toString());
        }

        // When latest version cannot be detected, the version must always be provided
        if (!$isInteractiveInput) { // we have nowhere to get the version number in non-interactive mode
            throw new CommandException('Auto-detection of latest Selenium version failed - version must be provided');
        }

        // In interactive mode version must specified
        return $this->io->ask($questionText, null, function ($answer) {
            if (empty($answer)) {
                throw new CommandException('Please provide version to download (latest version auto-detect failed)');
            }

            return $answer;
        });
    }

    private function printLinkToWiki(): void
    {
        $this->io->suggestion(
            'What now? Learn how to start the Selenium server: '
            . 'https://github.com/lmc-eu/steward/wiki/Selenium-server-&-browser-drivers'
        );
    }
}
