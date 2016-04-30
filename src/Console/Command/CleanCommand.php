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
        $logsDir = $input->getOption(RunCommand::OPTION_LOGS_DIR);

        if (!realpath($logsDir)) {
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

        $fs = new Filesystem();

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $fs->remove($file->getRealPath());
        }
    }
}
