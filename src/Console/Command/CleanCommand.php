<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Publisher\XmlPublisher;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Clean previous log files in the logs directory
 */
class CleanCommand extends Command
{
    /** @var Filesystem */
    private $filesystem;

    /**
     * @param Filesystem $filesystem
     */
    public function setFilesystem(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('clean')
            ->setDescription('Clean logs directory')
            ->addOption(
                RunCommand::OPTION_LOGS_DIR,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to directory with logs',
                STEWARD_BASE_DIR . '/logs'
            );

        $this->getDispatcher()->dispatch(CommandEvents::CONFIGURE, new BasicConsoleEvent($this));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $defaultLogsDir = $this->getDefinition()->getOption(RunCommand::OPTION_LOGS_DIR)->getDefault();
        $logsDir = $input->getOption(RunCommand::OPTION_LOGS_DIR);

        // If default path to logs directory is used and it does not exist, create the directory
        if ($logsDir == $defaultLogsDir) {
            $this->createLogsDirectoryIfNotExists($logsDir);
        }

        if (!realpath($logsDir) || !is_writable($logsDir)) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot clean logs directory "%s", make sure it is accessible.',
                    $logsDir
                )
            );
        }

        $this->cleanDirectory($logsDir);

        return 0;
    }

    /**
     * @codeCoverageIgnore
     * @return Filesystem
     */
    protected function getFilesystem()
    {
        if (!$this->filesystem) {
            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }

    private function createLogsDirectoryIfNotExists($logsDir)
    {
        $filesystem = $this->getFilesystem();

        if (!$filesystem->exists($logsDir)) {
            $filesystem->mkdir($logsDir);
        }
    }

    private function cleanDirectory($logsDir)
    {
        $finder = (new Finder())
            ->files()
            ->in($logsDir)
            ->depth('== 0')
            ->name('*.html')
            ->name('*.png')
            ->name('*.xml')
            ->notName(XmlPublisher::FILE_NAME);

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $this->getFilesystem()->remove($file->getRealPath());
        }
    }
}
