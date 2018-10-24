<?php declare(strict_types=1);

namespace Lmc\Steward\Process;

use Lmc\Steward\Console\Command\RunCommand;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Configuration\ConfigOptions;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use Lmc\Steward\Publisher\AbstractPublisher;
use Lmc\Steward\Utils\Strings;
use Nette\Reflection\AnnotationsParser;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

/**
 * Class to encapsulate creation of ProcessSet from files
 */
class ProcessSetCreator
{
    /** @var RunCommand */
    protected $command;
    /** @var InputInterface */
    protected $input;
    /** @var OutputInterface */
    protected $output;
    /** @var ProcessSet */
    protected $processSet;
    /** @var AbstractPublisher */
    protected $publisher;
    /** @var array */
    protected $config;

    public function __construct(
        RunCommand $command,
        InputInterface $input,
        OutputInterface $output,
        AbstractPublisher $publisher,
        array $config
    ) {
        $this->command = $command;
        $this->input = $input;
        $this->output = $output;
        $this->publisher = $publisher;
        $this->config = $config;
    }

    /**
     * Create ProcessSet from given files, optionally filtering by given $groups and $excludeGroups
     *
     * @param array $groups Groups to be run
     * @param array $excludeGroups Groups to be excluded
     * @param string $filter Filter test cases by given name
     * @param bool $ignoreDelays Should test delays be ignored?
     */
    public function createFromFiles(
        Finder $files,
        array $groups,
        array $excludeGroups,
        string $filter = null,
        bool $ignoreDelays = false
    ): ProcessSet {
        $files->sortByName();
        $processSet = $this->getProcessSet();

        if ($this->output->isVeryVerbose()) {
            if (!empty($groups) || !empty($excludeGroups) || !empty($filter)) {
                $this->output->writeln('Filtering testcases:');
            }
            if (!empty($groups)) {
                $this->output->writeln(sprintf(' - by group(s): %s', implode(', ', $groups)));
            }
            if (!empty($excludeGroups)) {
                $this->output->writeln(sprintf(' - excluding group(s): %s', implode(', ', $excludeGroups)));
            }
            if (!empty($filter)) {
                $this->output->writeln(sprintf(' - by testcase/test name: %s', $filter));
            }
        }

        $testCasesNum = 0;
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $fileName = $file->getRealPath();
            $className = $this->getClassNameFromFile($fileName);
            $annotations = $this->getClassAnnotations($className, $fileName);

            if ($excludingGroups = $this->getExcludingGroups($excludeGroups, $annotations)) {
                $this->output->writeln(
                    sprintf('Excluding testcase file %s with group %s', $fileName, implode(', ', $excludingGroups)),
                    OutputInterface::VERBOSITY_DEBUG
                );
                continue;
            }

            // Filter out test-cases without any matching group
            if (!empty($groups)) {
                if (!array_key_exists('group', $annotations)
                    || !count($matchingGroups = array_intersect($groups, $annotations['group']))
                ) {
                    continue;
                }

                $this->output->writeln(
                    sprintf(
                        'Found testcase file #%d in group %s: %s',
                        ++$testCasesNum,
                        implode(', ', $matchingGroups),
                        $fileName
                    ),
                    OutputInterface::VERBOSITY_DEBUG
                );
            } else {
                $this->output->writeln(
                    sprintf('Found testcase file #%d: %s', ++$testCasesNum, $fileName),
                    OutputInterface::VERBOSITY_DEBUG
                );
            }

            $phpunitArgs = [
                '--log-junit=' . $this->config[ConfigOptions::LOGS_DIR] . '/'
                . Strings::toFilename($className)
                . '.xml',
                '--configuration=' . realpath(__DIR__ . '/../phpunit.xml'),
            ];

            if (!empty($filter)) {
                $phpunitArgs[] = '--filter=' . $filter;
            }

            // If ANSI output is enabled, turn on colors in PHPUnit
            if ($this->output->isDecorated()) {
                $phpunitArgs[] = '--colors=always';
            }

            $processWrapper = new ProcessWrapper(
                $this->buildProcess($fileName, $phpunitArgs),
                $className,
                $this->publisher
            );

            if (!$ignoreDelays) {
                $this->setupProcessDelays($processWrapper, $annotations);
            }

            $processSet->add($processWrapper);
        }

        return $processSet;
    }

    /**
     * Build Process instance for each testcase file
     */
    protected function buildProcess(string $fileName, array $phpunitArgs = []): Process
    {
        $capabilities = (new KeyValueCapabilityOptionsParser())
            ->parse($this->input->getOption(RunCommand::OPTION_CAPABILITY));

        $env = [
            'BROWSER_NAME' => $this->input->getArgument(RunCommand::ARGUMENT_BROWSER),
            'ENV' => mb_strtolower($this->input->getArgument(RunCommand::ARGUMENT_ENVIRONMENT)),
            'CAPABILITY' => json_encode($capabilities),
            'CAPABILITIES_RESOLVER' => $this->config[ConfigOptions::CAPABILITIES_RESOLVER],
            'SERVER_URL' => $this->input->getOption(RunCommand::OPTION_SERVER_URL),
            'LOGS_DIR' => $this->config[ConfigOptions::LOGS_DIR],
            'DEBUG' => $this->output->isDebug() ? '1' : '0',
        ];

        $dispatcher = $this->command->getDispatcher();
        $dispatcher->dispatch(
            CommandEvents::RUN_TESTS_PROCESS,
            $processEvent = new RunTestsProcessEvent(
                $this->command,
                $this->input,
                $this->output,
                $env,
                $phpunitArgs
            )
        );

        $phpunitExecutable = realpath(__DIR__ . '/../../bin/phpunit-steward');

        $commandLine = array_merge([PHP_BINARY, $phpunitExecutable], $processEvent->getArgs(), [$fileName]);

        return new Process($commandLine, STEWARD_BASE_DIR, $processEvent->getEnvironmentVars(), null, 3600);
    }

    protected function getProcessSet(): ProcessSet
    {
        if ($this->processSet === null) {
            $this->processSet = new ProcessSet();
            $this->processSet->setPublisher($this->publisher);
        }

        return $this->processSet;
    }

    /**
     * Get array of groups that cause this class to be excluded.
     *
     * @return array Empty array is returned if class should not be excluded.
     */
    private function getExcludingGroups(array $excludeGroups, array $annotations): array
    {
        $excludingGroups = [];

        if (!empty($excludeGroups) && array_key_exists('group', $annotations)) {
            if (!empty(array_intersect($excludeGroups, $annotations['group']))) {
                $excludingGroups = array_intersect($excludeGroups, $annotations['group']);
            }
        }

        return $excludingGroups;
    }

    private function getClassNameFromFile(string $fileName): string
    {
        // Parse classes from the testcase file
        $classes = AnnotationsParser::parsePhp(\file_get_contents($fileName));

        if (empty($classes)) {
            throw new \RuntimeException(sprintf('No class found in file "%s"', $fileName));
        }

        if (count($classes) > 1) {
            throw new \RuntimeException(
                sprintf(
                    'File "%s" contains definition of %d classes. However, each class must be defined in its own'
                    . ' separate file.',
                    $fileName,
                    count($classes)
                )
            );
        }

        return key($classes);
    }

    private function setupProcessDelays(ProcessWrapper $processWrapper, array $annotations): void
    {
        $delayAfter = !empty($annotations['delayAfter']) ? current($annotations['delayAfter']) : '';
        $delayMinutes = !empty($annotations['delayMinutes']) ? current($annotations['delayMinutes']) : null;

        if ($delayAfter) {
            $processWrapper->setDelay($delayAfter, $delayMinutes);
        } elseif ($delayMinutes !== null && empty($delayAfter)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Testcase "%s" has defined delay %d minutes, '
                    . 'but doesn\'t have defined the testcase to run after',
                    $processWrapper->getClassName(),
                    $delayMinutes
                )
            );
        }
    }

    /**
     * Get annotations for the first class in testcase (one file = one class)
     */
    private function getClassAnnotations(string $className, string $fileName): array
    {
        try {
            return AnnotationsParser::getAll(new \ReflectionClass($className));
        } catch (\ReflectionException $e) {
            throw new \RuntimeException(
                sprintf(
                    'Error loading class "%s" from file "%s". Make sure the class name and namespace matches '
                    . 'the file path.',
                    $className,
                    $fileName
                )
            );
        }
    }
}
