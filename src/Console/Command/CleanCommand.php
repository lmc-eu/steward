<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Configuration\ConfigOptions;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Publisher\XmlPublisher;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
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

    public function __construct(EventDispatcher $dispatcher, $name = null)
    {
        $this->filesystem = new Filesystem();

        parent::__construct($dispatcher, $name);
    }

    /**
     * @internal
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
                STEWARD_BASE_DIR . DIRECTORY_SEPARATOR . 'logs'
            );

        $this->getDispatcher()->dispatch(CommandEvents::CONFIGURE, new BasicConsoleEvent($this));
    }

    protected function resolveConfiguration(InputInterface $input)
    {
        if ($this->isDefaultLogsDirUsed($input)) {
            $this->createLogsDirectoryIfNotExists(
                $input->getOption(RunCommand::OPTION_LOGS_DIR)
            );
        }

        return parent::resolveConfiguration($input);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logsDir = $this->config[ConfigOptions::LOGS_DIR];

        $this->cleanDirectory($logsDir);

        return 0;
    }

    /**
     * @param InputInterface $input
     * @return bool
     */
    protected function isDefaultLogsDirUsed(InputInterface $input)
    {
        $logsDir = $input->getOption(RunCommand::OPTION_LOGS_DIR);
        $defaultLogsDir = $this->getDefinition()->getOption(RunCommand::OPTION_LOGS_DIR)->getDefault();

        return $logsDir == $defaultLogsDir;
    }

    /**
     * @param string $logsDir
     */
    private function createLogsDirectoryIfNotExists($logsDir)
    {
        if (!$this->filesystem->exists($logsDir)) {
            $this->filesystem->mkdir($logsDir);
        }
    }

    /**
     * @param string $logsDir
     */
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
            $this->filesystem->remove($file->getRealPath());
        }
    }
}
