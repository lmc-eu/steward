<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Selenium\Downloader;
use OndraM\CiDetector\CiDetector;
use Symfony\Component\Console\Exception\RuntimeException;
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
     * @var string
     */
    protected $targetDir = '/vendor/bin';

    /**
     * @internal
     * @param Downloader $downloader
     */
    public function setDownloader(Downloader $downloader)
    {
        $this->downloader = $downloader;
    }

    /**
     * Configure command
     */
    protected function configure()
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
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
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

        if (!$downloadedSize) {
            $this->io->error('Error downloading file :-(');

            return 1;
        }

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
     * @return Downloader
     */
    protected function getDownloader()
    {
        if (!$this->downloader) {
            $this->downloader = new Downloader(STEWARD_BASE_DIR . $this->targetDir);
        }

        return $this->downloader;
    }

    /**
     * @param $isInteractiveInput
     * @return string
     */
    private function askForVersion($isInteractiveInput)
    {
        $latestVersion = Downloader::getLatestVersion();

        $questionText = 'Enter Selenium server version to install';

        if (!empty($latestVersion)) {
            return $this->io->ask($questionText, $latestVersion);
        }

        // When latest version cannot be detected, the version must always be provided
        if (!$isInteractiveInput) { // we have nowhere to get the version number in non-interactive mode
            throw new RuntimeException('Auto-detection of latest Selenium version failed - version must be provided');
        }

        // in interactive mode version must specified
        return $this->io->ask($questionText, null, function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('Please provide version to download (latest version auto-detect failed)');
            }

            return $answer;
        });
    }

    private function printLinkToWiki()
    {
        $this->io->note(
            'What now? Learn how to start the Selenium server: '
            . 'https://github.com/lmc-eu/steward/wiki/Selenium-server-&-browser-drivers'
        );
    }
}
