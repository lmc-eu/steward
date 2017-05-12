<?php

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
use Symfony\Component\Process\ProcessBuilder;

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

    /**
     * @param RunCommand $command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param AbstractPublisher $publisher
     * @param array $config
     */
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
     * @param Finder $files
     * @param array $groups Groups to be run
     * @param array $excludeGroups Groups to be excluded
     * @param string $filter filter test cases by name
     * @param bool $ignoreDelays Should test delays be ignored?
     * @return ProcessSet
     */
    public function createFromFiles(
        Finder $files,
        array $groups,
        array $excludeGroups,
        $filter = null,
        $ignoreDelays = false
    ) {
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
     *
     * @param string $fileName
     * @param array $phpunitArgs
     * @return Process
     */
    protected function buildProcess($fileName, array $phpunitArgs = [])
    {
        $processBuilder = new ProcessBuilder();

        $dispatcher = $this->command->getDispatcher();
        $dispatcher->dispatch(
            CommandEvents::RUN_TESTS_PROCESS,
            $processEvent = new RunTestsProcessEvent(
                $this->command,
                $this->input,
                $this->output,
                $processBuilder,
                $phpunitArgs
            )
        );

        $capabilities = (new KeyValueCapabilityOptionsParser())
            ->parse($this->input->getOption(RunCommand::OPTION_CAPABILITY));

        $processBuilder
            ->setEnv('BROWSER_NAME', $this->input->getArgument(RunCommand::ARGUMENT_BROWSER))
            ->setEnv('ENV', mb_strtolower($this->input->getArgument(RunCommand::ARGUMENT_ENVIRONMENT)))
            ->setEnv('CAPABILITY', json_encode($capabilities))
            ->setEnv('CAPABILITIES_RESOLVER', $this->config[ConfigOptions::CAPABILITIES_RESOLVER])
            ->setEnv('SERVER_URL', $this->input->getOption(RunCommand::OPTION_SERVER_URL))
            ->setEnv('FIXTURES_DIR', $this->config[ConfigOptions::FIXTURES_DIR])
            ->setEnv('LOGS_DIR', $this->config[ConfigOptions::LOGS_DIR])
            ->setEnv('DEBUG', $this->output->isDebug() ? '1' : '0')
            ->setPrefix(STEWARD_BASE_DIR . '/vendor/bin/phpunit')
            ->setArguments(array_merge($processEvent->getArgs(), [$fileName]))
            ->setTimeout(3600); // 1 hour timeout to end possibly stuck processes

        return $processBuilder->getProcess();
    }

    /**
     * @return ProcessSet
     */
    protected function getProcessSet()
    {
        if (!$this->processSet) {
            $this->processSet = new ProcessSet();
            $this->processSet->setPublisher($this->publisher);
        }

        return $this->processSet;
    }

    /**
     * Get array of groups that cause this class to be excluded.
     *
     * @param array $excludeGroups
     * @param array $annotations
     * @return array Empty if class should not be excluded.
     */
    private function getExcludingGroups(array $excludeGroups, array $annotations)
    {
        $excludingGroups = [];

        if (!empty($excludeGroups) && array_key_exists('group', $annotations)) {
            if (!empty(array_intersect($excludeGroups, $annotations['group']))) {
                $excludingGroups = array_intersect($excludeGroups, $annotations['group']);
            }
        }

        return $excludingGroups;
    }

    /**
     * @param $fileName
     * @return string
     */
    private function getClassNameFromFile($fileName)
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

    /**
     * @param ProcessWrapper $processWrapper
     * @param array $annotations
     */
    private function setupProcessDelays(ProcessWrapper $processWrapper, array $annotations)
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
     *
     * @param string $className
     * @param string $fileName
     * @return array
     */
    private function getClassAnnotations($className, $fileName)
    {
        try {
            $annotations = AnnotationsParser::getAll(new \ReflectionClass($className));

            return $annotations;
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
