<?php

namespace Lmc\Steward\Process;

use Lmc\Steward\Console\Command\RunTestsCommand;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use Lmc\Steward\Publisher\AbstractPublisher;
use Nette\Reflection\AnnotationsParser;
use Nette\Utils\Strings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Class to encapsulate ProcessSet creation during RunTestsCommand
 */
class ProcessSetCreator
{
    /** @var RunTestsCommand */
    protected $command;
    /** @var InputInterface */
    protected $input;
    /** @var OutputInterface */
    protected $output;
    /** @var ProcessSet */
    protected $processSet;
    /** @var AbstractPublisher */
    protected $publisher;

    /**
     * @param RunTestsCommand $command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param AbstractPublisher $publisher
     */
    public function __construct(
        RunTestsCommand $command,
        InputInterface $input,
        OutputInterface $output,
        AbstractPublisher $publisher
    ) {
        $this->command = $command;
        $this->input = $input;
        $this->output = $output;
        $this->publisher = $publisher;
    }

    /**
     * Create ProcessSet from given files, optionally filtering by given $groups and $excludeGroups
     *
     * @param Finder $files
     * @param array $groups Groups to be run
     * @param array $excludeGroups Groups to be excluded
     * @return ProcessSet
     */
    public function createFromFiles(Finder $files, array $groups = null, array $excludeGroups = null)
    {
        if ($groups || $excludeGroups) {
            $this->output->writeln('Filtering testcases:');
        }
        if ($groups) {
            $this->output->writeln(sprintf(' - by group(s): %s', implode(', ', $groups)));
        }
        if ($excludeGroups) {
            $this->output->writeln(sprintf(' - excluding group(s): %s', implode(', ', $excludeGroups)));
        }

        $processSet = $this->getProcessSet();

        $testCasesNum = 0;
        foreach ($files as $file) {
            $fileName = $file->getRealpath();
            // Parse classes from the testcase file
            $classes = AnnotationsParser::parsePhp(\file_get_contents($fileName));

            // Get annotations for the first class in testcase (one file = one class)
            $annotations = AnnotationsParser::getAll(new \ReflectionClass(key($classes)));

            // Filter out test-cases having any of excluded groups
            if ($excludeGroups && array_key_exists('group', $annotations)
                && count($excludingGroups = array_intersect($excludeGroups, $annotations['group']))
            ) {
                if ($this->output->isDebug()) {
                    $this->output->writeln(
                        sprintf(
                            'Excluding testcase file %s with group %s',
                            $fileName,
                            implode(', ', $excludingGroups)
                        )
                    );
                }
                continue;
            }

            // Filter out test-cases without any matching group
            if ($groups) {
                if (!array_key_exists('group', $annotations)
                    || !count($matchingGroups = array_intersect($groups, $annotations['group']))
                ) {
                    continue;
                }

                if ($this->output->isDebug()) {
                    $this->output->writeln(
                        sprintf(
                            'Found testcase file #%d in group %s: %s',
                            ++$testCasesNum,
                            implode(', ', $matchingGroups),
                            $fileName
                        )
                    );
                }
            } else {
                if ($this->output->isDebug()) {
                    $this->output->writeln(sprintf('Found testcase file #%d: %s', ++$testCasesNum, $fileName));
                }
            }

            $phpunitArgs = [
                '--log-junit=logs/'
                . Strings::webalize(key($classes), null, $lower = false)
                . '.xml',
                '--configuration=' . realpath(__DIR__ . '/../phpunit.xml'),
            ];

            // If ANSI output is enabled, turn on colors in PHPUnit
            if ($this->output->isDecorated()) {
                $phpunitArgs[] = '--colors=always';
            }

            $processSet->add(
                $this->buildProcess($fileName, $phpunitArgs),
                key($classes),
                $delayAfter = !empty($annotations['delayAfter']) ? current($annotations['delayAfter']) : '',
                $delayMinutes = !empty($annotations['delayMinutes']) ? current($annotations['delayMinutes']) : null
            );
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

        $process = $processBuilder
            ->setEnv('BROWSER_NAME', $this->input->getArgument('browser'))
            ->setEnv('ENV', strtolower($this->input->getArgument('environment')))
            ->setEnv('SERVER_URL', $this->input->getOption('server-url'))
            ->setEnv('PUBLISH_RESULTS', $this->input->getOption('publish-results') ? '1' : '0')
            ->setEnv('FIXTURES_DIR', $this->input->getOption('fixtures-dir'))
            ->setEnv('LOGS_DIR', $this->input->getOption('logs-dir'))
            ->setEnv('DEBUG', $this->output->isDebug() ? '1' : '0')
            ->setPrefix(STEWARD_BASE_DIR . '/vendor/bin/phpunit')
            ->setArguments(array_merge($processEvent->getArgs(), [$fileName]))
            ->setTimeout(3600) // 1 hour timeout to end possibly stuck processes
            ->getProcess();

        return $process;
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
}
