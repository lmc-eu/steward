<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Exception\CommandException;
use Lmc\Steward\Selenium\Downloader;
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
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('install')
            ->setDescription('Download latest Selenium standalone server jar file')
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'Specific Selenium version to install'
            );

        $this->getDispatcher()->dispatch(CommandEvents::CONFIGURE, new BasicConsoleEvent($this));
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

        $version = $input->getArgument('version'); // exact version could be specified as argument

        if (!$version) {
            $version = $this->askForVersion($input->isInteractive());
        }

        if ($verboseOutput) {
            $this->io->note(
                sprintf(
                    'Downloading Selenium standalone server version %s...%s',
                    $version,
                    (!(new CiDetector())->isCiDetected() ? ' Just for you <3!' : '')
                )
            );
        }

        $downloader = $this->getDownloader();
        $downloader->setVersion($version);
        $targetPath = realpath($downloader->getFilePath());

        if ($this->io->isVerbose()) {
            $this->io->note(sprintf('Download URL: %s', $downloader->getFileUrl()));
        }

        if ($downloader->isAlreadyDownloaded()) {
            if ($verboseOutput) {
                $this->io->note(
                    sprintf('File "%s" already exists - won\'t be downloaded again.', basename($targetPath))
                );

                $this->io->note('Path to file: ' . $targetPath);
                $this->printLinkToWiki();
            } else {
                $this->io->writeln($targetPath); // In non-verbose mode only output path to the file
            }

            return 0;
        }

        if ($verboseOutput) {
            $this->io->note('Downloading may take a while - its ~20 MB...');
        }

        $downloadedSize = $downloader->download();
        $downloadedFilePath = realpath($downloader->getFilePath());

        if ($verboseOutput) {
            $this->io->success(
                sprintf('Downloaded %d MB, file saved successfully.', round($downloadedSize / 1024 / 1024, 1))
            );
            $this->io->note('Path to file: ' . $downloadedFilePath);
            $this->printLinkToWiki();
        } else {
            $this->io->writeln($downloadedFilePath); // In non-verbose mode only output path to the file
        }

        return 0;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getDownloader(): Downloader
    {
        if ($this->downloader === null) {
            $this->downloader = new Downloader(STEWARD_BASE_DIR . $this->targetDir);
        }

        return $this->downloader;
    }

    private function askForVersion(bool $isInteractiveInput): string
    {
        $latestVersion = Downloader::getLatestVersion();

        $questionText = 'Enter Selenium server version to install';

        if ($latestVersion !== null) {
            return $this->io->ask($questionText, $latestVersion);
        }

        // When latest version cannot be detected, the version must always be provided
        if (!$isInteractiveInput) { // we have nowhere to get the version number in non-interactive mode
            throw new CommandException('Auto-detection of latest Selenium version failed - version must be provided');
        }

        // in interactive mode version must specified
        return $this->io->ask($questionText, null, function ($answer) {
            if (empty($answer)) {
                throw new CommandException('Please provide version to download (latest version auto-detect failed)');
            }

            return $answer;
        });
    }

    private function printLinkToWiki(): void
    {
        $this->io->note(
            'What now? Learn how to start the Selenium server: '
            . 'https://github.com/lmc-eu/steward/wiki/Selenium-server-&-browser-drivers'
        );
    }
}
