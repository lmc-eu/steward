<?php

namespace Lmc\Steward\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;

/**
 * Install (download) Selenium standalone server.
 *
 * @copyright LMC s.r.o.
 */
class InstallCommand extends Command
{
    /**
     * Selenium storage URL
     * @var string
     */
    protected $storageUrl = 'https://selenium-release.storage.googleapis.com';

    /**
     * Target directory to store the selenium server (relatively to __DIR__)
     * @var string
     */
    protected $targetDir = '/../../bin';

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
    }

    /**
     * Execute command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $version = $input->getArgument('version'); // exact version could be specified as argument

        if (!$version) {
            $latestVersion = $this->getLatestVersion();

            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');

            $question = new Question(
                '<question>Enter Selenium server version to install:</question> '
                . ($latestVersion ? "[$latestVersion] " : ''),
                $latestVersion
            );
            $version = $questionHelper->ask($input, $output, $question);
        }

        $output->writeln(
            'Steward is now downloading Selenium standalone server...'
            . (!getenv('JOB_NAME') ? ' Just for you <3!' : '') // in jenkins it is not just for you, sorry
        );

        $versionParts = explode('.', $version);

        $fileName = 'selenium-server-standalone-' . $version . '.jar';
        $fileUrl = $this->storageUrl . '/' . $versionParts[0] . '.' . $versionParts[1] . '/' . $fileName;

        $targetPath = realpath(__DIR__ . $this->targetDir) . DIRECTORY_SEPARATOR . $fileName;

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

    /**
     * Get latest released version of Selenium server. If not found, null is returned.
     * @return string|null
     */
    protected function getLatestVersion()
    {
        $data = file_get_contents($this->storageUrl);
        if (!$data) {
            return;
        }
        libxml_use_internal_errors(true); // disable errors from being thrown
        $xml = simplexml_load_string($data);

        if (!$xml) {
            return;
        }

        $releases = $xml->xpath('//*[text()[contains(.,"selenium-server-standalone")]]');
        $lastRelease = end($releases); // something like "2.42/selenium-server-standalone-2.42.2.jar"

        $lastVersion = preg_replace('/.*standalone-([0-9\.]*)\.jar/', '$1', $lastRelease);
        if ($lastRelease == $lastVersion) { // regexp not matched
            return;
        }

        return $lastVersion;
    }
}
