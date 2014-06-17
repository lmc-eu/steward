<?php

namespace Lmc\Steward;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Install (download) Selenium standalone server.
 *
 * @copyright LMC s.r.o.
 */
class InstallCommand extends Command
{
    /**
     * Selenium version to download
     * @var string
     */
    protected $version = '2.42.2';

    /**
     * Selenium storage URL
     * @var string
     */
    protected $storageUrl = 'https://selenium-release.storage.googleapis.com';

    /**
     * Target directory to store the selenium server
     * @var string
     */
    protected $targetDir = './bin';


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
                'Overwrite Selenium version to install',
                $this->version
            );
    }

    /**
     * Execute command
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(
            'Steward is downloading Selenium standalone server...'
            . (!getenv('JOB_NAME') ? ' Just for you <3!' : '') // in jenkins it is not just for you, sorry
        );

        $version = $input->getArgument('version');
        $versionParts = explode('.', $version);

        $fileName = 'selenium-server-standalone-' . $version . '.jar';
        $fileUrl = $this->storageUrl . '/' . $versionParts[0] . '.' . $versionParts[1] . '/' . $fileName;

        $targetPath = realpath($this->targetDir) . DIRECTORY_SEPARATOR . $fileName;

        $output->writeln(sprintf('Version: %s', $version));
        $output->writeln(sprintf('File URL: %s', $fileUrl));
        $output->writeln(sprintf('Target file path: %s', $targetPath));

        if (file_exists($targetPath)) {
            $output->writeln(
                sprintf(
                    'File "%s" already exists in directory "%s" - won\'t be downloaded again.',
                    $fileName,
                    realpath($this->targetDir)
                )
            );
            return 0;
        }

        $output->writeln('Downloading (may take a while - its over 30 MB)...');
        $fp = fopen($fileUrl, 'r');
        $downloadedSize = file_put_contents($targetPath, $fp);

        if (!$downloadedSize) {
            $output->writeln('Error downloading file :-(');
            return 1;
        }

        $output->writeln('Downloaded ' . $downloadedSize . ' bytes, file saved succesfully.');
        return 0;
    }
}
