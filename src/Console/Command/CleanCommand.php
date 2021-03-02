<?php declare(strict_types=1);

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

    public function __construct(EventDispatcher $dispatcher, string $name = null)
    {
        $this->filesystem = new Filesystem();

        parent::__construct($dispatcher, $name);
    }

    /**
     * @internal
     */
    public function setFilesystem(Filesystem $filesystem): void
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Configure command
     */
    protected function configure(): void
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

        $this->getDispatcher()->dispatch(new BasicConsoleEvent($this), CommandEvents::CONFIGURE);
    }

    protected function resolveConfiguration(InputInterface $input): array
    {
        if ($this->isDefaultLogsDirUsed($input)) {
            $this->createLogsDirectoryIfNotExists(
                $input->getOption(RunCommand::OPTION_LOGS_DIR)
            );
        }

        return parent::resolveConfiguration($input);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logsDir = $this->config[ConfigOptions::LOGS_DIR];

        $this->cleanDirectory($logsDir);

        return 0;
    }

    protected function isDefaultLogsDirUsed(InputInterface $input): bool
    {
        $logsDir = $input->getOption(RunCommand::OPTION_LOGS_DIR);
        $defaultLogsDir = $this->getDefinition()->getOption(RunCommand::OPTION_LOGS_DIR)->getDefault();

        return $logsDir === $defaultLogsDir;
    }

    private function createLogsDirectoryIfNotExists(string $logsDir): void
    {
        if (!$this->filesystem->exists($logsDir)) {
            $this->filesystem->mkdir($logsDir);
        }
    }

    private function cleanDirectory(string $logsDir): void
    {
        /** @var Finder $finder */
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
