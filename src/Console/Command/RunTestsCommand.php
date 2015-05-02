<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use Lmc\Steward\Process\MaxTotalDelayStrategy;
use Lmc\Steward\Process\ProcessSet;
use Lmc\Steward\Publisher\XmlPublisher;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Finder\Finder;
use Nette\Reflection\AnnotationsParser;
use Nette\Utils\Strings;

/**
 * Run tests command is used to start Steward test planner and execute tests one by one,
 * optionally with defined delay and relations.
 */
class RunTestsCommand extends Command
{
    /** @var SeleniumServerAdapter */
    protected $seleniumAdapter;

    /**
     * @param SeleniumServerAdapter $seleniumAdapter
     */
    public function setSeleniumAdapter(SeleniumServerAdapter $seleniumAdapter)
    {
        $this->seleniumAdapter = $seleniumAdapter;
    }

    /**
     * @return SeleniumServerAdapter
     */
    public function getSeleniumAdapter()
    {
        if (!$this->seleniumAdapter) {
            $this->seleniumAdapter = new SeleniumServerAdapter();
        }

        return $this->seleniumAdapter;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('run-tests')
            ->setDescription('Run tests planner and execute tests')
            ->addArgument(
                'environment',
                InputArgument::REQUIRED,
                'Environment name (must be specified to avoid unintentional run against production)'
            )
            ->addArgument(
                'browser',
                InputArgument::REQUIRED,
                'Browser in which tests should be run'
            )
            ->addOption(
                'server-url',
                null,
                InputOption::VALUE_REQUIRED,
                'Selenium server (hub) hub hostname and port',
                'http://localhost:4444'
            )
            ->addOption(
                'tests-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to directory with tests',
                realpath(STEWARD_BASE_DIR . '/tests')
            )
            ->addOption(
                'fixtures-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Base path to directory with fixture files',
                realpath(STEWARD_BASE_DIR . '/tests')
            )->addOption(
                'logs-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to directory with logs',
                realpath(STEWARD_BASE_DIR . '/logs')
            )
            ->addOption(
                'pattern',
                null,
                InputOption::VALUE_REQUIRED,
                'Pattern for test files to be run',
                '*Test.php'
            )
            ->addOption(
                'group',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Only run testcases with specified @group of this name'
            )
            ->addOption(
                'exclude-group',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Exclude testcases with specified @group from being run'
            )
            ->addOption(
                'publish-results',
                null,
                InputOption::VALUE_NONE,
                'Publish test results to test storage'
            );

        $this->getDispatcher()->dispatch(CommandEvents::CONFIGURE, new BasicConsoleEvent($this));
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(
            'Steward is running the tests...'
            . (!getenv('JOB_NAME') ? ' Just for you <3!' : '') // in jenkins it is not just for you, sorry
        );

        $output->writeln(sprintf('Browser: %s', $input->getArgument('browser')));
        $output->writeln(sprintf('Environment: %s', $input->getArgument('environment')));

        // Tests directories exists
        $testDirectoriesResult = $this->testDirectories(
            $input,
            $output,
            [
                $this->getDefinition()->getOption('tests-dir'),
                $this->getDefinition()->getOption('logs-dir'),
                $this->getDefinition()->getOption('fixtures-dir'),
            ]
        );
        if (!$testDirectoriesResult) {
            return 1;
        }

        if ($output->isDebug()) {
            $output->writeln(sprintf('Base path to fixtures results: %s', $input->getOption('fixtures-dir')));
            $output->writeln(sprintf('Path to logs: %s', $input->getOption('logs-dir')));
            $output->writeln(sprintf('Publish results: %s', ($input->getOption('publish-results')) ? 'yes' : 'no'));
        }

        $this->getDispatcher()->dispatch(
            CommandEvents::RUN_TESTS_INIT,
            new ExtendedConsoleEvent($this, $input, $output)
        );

        if (!$this->testSeleniumConnection($output, $input->getOption('server-url'))) {
            return 1;
        }

        // Find all files holding test-cases
        $files = (new Finder())
            ->useBestAdapter()
            ->files()
            ->in($input->getOption('tests-dir'))
            ->name($input->getOption('pattern'));

        // Build set of processes prepared to be run
        $processSet = $this->prepareProcessSet(
            $input,
            $output,
            $files
        );

        if (!count($processSet)) {
            $output->writeln('No testcases matched given criteria, exiting.');

            return 1;
        }

        // Optimize processes order
        $processSet->optimizeOrder(new MaxTotalDelayStrategy());

        // Initialize first processes that should be run
        $processSet->dequeueProcessesWithoutDelay($output->isDebug() ? $output : null);

        // Start execution loop
        $this->executionLoop($output, $processSet);
    }

    /**
     * Fill ProcessSet with test-cases from $files
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param Finder $files
     * @return ProcessSet
     */
    protected function prepareProcessSet(InputInterface $input, OutputInterface $output, $files)
    {
        $output->writeln('Searching for testcases:');
        if ($input->getOption('group')) {
            $output->writeln(sprintf(' - in group(s): %s', implode(', ', $input->getOption('group'))));
        }
        if ($input->getOption('exclude-group')) {
            $output->writeln(sprintf(' - excluding group(s): %s', implode(', ', $input->getOption('exclude-group'))));
        }
        $output->writeln(sprintf(' - in directory "%s"', $input->getOption('tests-dir')));
        $output->writeln(sprintf(' - by pattern "%s"', $input->getOption('pattern')));

        $xmlPublisher = new XmlPublisher($input->getArgument('environment'), null, null);
        $xmlPublisher->setFileDir($input->getOption('logs-dir'));
        $xmlPublisher->clean();

        $processSet = new ProcessSet($xmlPublisher);

        $testCasesNum = 0;
        foreach ($files as $file) {
            $fileName = $file->getRealpath();
            // Parse classes from the testcase file
            $classes = AnnotationsParser::parsePhp(\file_get_contents($fileName));

            // Get annotations for the first class in testcase (one file = one class)
            $annotations = AnnotationsParser::getAll(new \ReflectionClass(key($classes)));

            // Filter out test-cases having any of excluded groups
            if ($input->getOption('exclude-group') && array_key_exists('group', $annotations)
                && count($excludingGroups = array_intersect($input->getOption('exclude-group'), $annotations['group']))
            ) {
                if ($output->isDebug()) {
                    $output->writeln(
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
            if ($input->getOption('group')) {
                if (!array_key_exists('group', $annotations)
                    || !count($matchingGroups = array_intersect($input->getOption('group'), $annotations['group']))
                ) {
                    continue;
                }

                if ($output->isDebug()) {
                    $output->writeln(
                        sprintf(
                            'Found testcase file #%d in group %s: %s',
                            ++$testCasesNum,
                            implode(', ', $matchingGroups),
                            $fileName
                        )
                    );
                }
            } else {
                if ($output->isDebug()) {
                    $output->writeln(sprintf('Found testcase file #%d: %s', ++$testCasesNum, $fileName));
                }
            }

            $phpunitArgs = [
                '--log-junit=logs/'
                . Strings::webalize(key($classes), null, $lower = false)
                . '.xml',
                '--configuration=' . realpath(__DIR__ . '/../../phpunit.xml'),
            ];

            // If ANSI output is enabled, turn on colors in PHPUnit
            if ($output->isDecorated()) {
                $phpunitArgs[] = '--colors';
            }

            // Prepare Processes for each testcase
            $processBuilder = new ProcessBuilder();

            $this->getDispatcher()->dispatch(
                CommandEvents::RUN_TESTS_PROCESS,
                $processEvent = new RunTestsProcessEvent($this, $input, $output, $processBuilder, $phpunitArgs)
            );

            $process = $processBuilder
                ->setEnv('BROWSER_NAME', $input->getArgument('browser'))
                ->setEnv('ENV', strtolower($input->getArgument('environment')))
                ->setEnv('SERVER_URL', $input->getOption('server-url'))
                ->setEnv('PUBLISH_RESULTS', $input->getOption('publish-results') ? '1' : '0')
                ->setEnv('FIXTURES_DIR', $input->getOption('fixtures-dir'))
                ->setEnv('LOGS_DIR', $input->getOption('logs-dir'))
                ->setEnv('DEBUG', $output->isDebug() ? '1' : '0')
                ->setPrefix(STEWARD_BASE_DIR . '/vendor/bin/phpunit')
                ->setArguments(array_merge($processEvent->getArgs(), [$fileName]))
                ->setTimeout(3600) // 1 hour timeout to end possibly stuck processes
                ->getProcess();

            $processSet->add(
                $process,
                key($classes),
                $delayAfter = !empty($annotations['delayAfter']) ? current($annotations['delayAfter']) : '',
                $delayMinutes = !empty($annotations['delayMinutes']) ? current($annotations['delayMinutes']) : null
            );
        }

        return $processSet;
    }

    /**
     * Start planner execution loop
     *
     * @param OutputInterface $output
     * @param ProcessSet $processSet
     */
    protected function executionLoop(OutputInterface $output, ProcessSet $processSet)
    {
        $counterWaitingOutput = 1;
        $counterProcessesLast = 0;
        // Iterate over prepared and queued until everything is done
        while (true) {
            $prepared = $processSet->get(ProcessSet::PROCESS_STATUS_PREPARED);
            $queued = $processSet->get(ProcessSet::PROCESS_STATUS_QUEUED);

            if (count($prepared) == 0 && count($queued) == 0) {
                $output->writeln('No tasks left, exiting the execution loop...');
                break;
            }

            // Start all prepared tasks and set status of not running as finished
            foreach ($prepared as $testClass => $processObject) {
                if (!$processObject->process->isStarted()) {
                    if ($output->isDebug()) {
                        $output->writeln(
                            sprintf(
                                'Running command for class "%s":' . "\n" . '%s',
                                $testClass,
                                $processObject->process->getCommandLine()
                            )
                        );
                    }
                    $processObject->process->start();
                    usleep(50000); // wait for a while (0,05 sec) to let processes be started in intended order

                    continue;
                }

                $timeoutError = $this->checkProcessTimeout($processObject->process, $testClass);
                if ($timeoutError) {
                    $output->writeln('<error>' . $timeoutError . '</error>');
                }

                $processOutput = $this->getProcessOutput($processObject->process);
                if ($processOutput) {
                    $output->write($processOutput);
                }

                // Mark no longer running processes as finished
                if (!$processObject->process->isRunning()) {
                    $output->writeln(sprintf('Process for class "%s" finished', $testClass));
                    $processSet->setStatus($testClass, ProcessSet::PROCESS_STATUS_DONE);
                    $processObject->finishedTime = time();
                }
            }

            // Add queued tasks to prepared if their dependent task is done and delay has passed
            $done = $processSet->get(ProcessSet::PROCESS_STATUS_DONE);
            $doneClasses = [];
            foreach ($done as $testClass => $processObject) {
                $doneClasses[] = $testClass;
            }
            foreach ($queued as $testClass => $processObject) {
                $delaySeconds = $processObject->delayMinutes * 60;

                if (in_array($processObject->delayAfter, $doneClasses)
                    && (time() - $done[$processObject->delayAfter]->finishedTime) > $delaySeconds
                ) {
                    $output->writeln(sprintf('Unqueing class "%s"', $testClass));
                    $processSet->setStatus($testClass, ProcessSet::PROCESS_STATUS_PREPARED);
                }
            }

            $countProcessesPrepared = count($processSet->get(ProcessSet::PROCESS_STATUS_PREPARED));
            $countProcessesQueued = count($processSet->get(ProcessSet::PROCESS_STATUS_QUEUED));
            $countProcessesDone = count($processSet->get(ProcessSet::PROCESS_STATUS_DONE));
            $counterProcesses = [$countProcessesPrepared, $countProcessesQueued, $countProcessesDone];
            // if the output didn't change, wait 10 seconds before printing it again
            if ($counterProcesses === $counterProcessesLast && $counterWaitingOutput % 10 !== 0) {
                $counterWaitingOutput++;
            } else {
                $output->writeln(
                    sprintf(
                        "[%s]: waiting (running: %d, queued: %d, done: %d)",
                        date("Y-m-d H:i:s"),
                        $countProcessesPrepared,
                        $countProcessesQueued,
                        $countProcessesDone
                    )
                );
                $counterWaitingOutput = 1;
            }
            $counterProcessesLast = $counterProcesses;
            sleep(1);
        }
    }

    /**
     * Check if process is not running longer then specified timeout, return error message if so.
     * @param Process $process Process instance
     * @param string $testClass Name of tested class
     * @return string|null Error message if process timeout exceeded
     */
    protected function checkProcessTimeout(Process $process, $testClass)
    {
        try {
            $process->checkTimeout();
        } catch (ProcessTimedOutException $e) {
            return sprintf(
                '[%s]: Process for class "%s" exceeded the time out of %d seconds and was killed.',
                date("Y-m-d H:i:s"),
                $testClass,
                $e->getExceededTimeout()
            );
        }
    }

    /**
     * Decorate and return Process output
     * @param Process $process
     * @return string
     */
    protected function getProcessOutput(Process $process)
    {
        $output = '';

        // Add standard process output
        if ($processOutput = $process->getIncrementalOutput()) {
            $processOutputLines = explode("\n", $processOutput);

            // color output lines containing "[WARN]"
            foreach ($processOutputLines as &$processOutputLine) {
                if (strpos($processOutputLine, '[WARN]') !== false) {
                    $processOutputLine = '<fg=black;bg=yellow>' . $processOutputLine . '</fg=black;bg=yellow>';
                } elseif (strpos($processOutputLine, '[DEBUG]') !== false) {
                    $processOutputLine = '<comment>' . $processOutputLine . '</comment>';
                }
            }
            $output .= implode("\n", $processOutputLines);
        }

        // Add error output
        if ($errorOutput = $process->getIncrementalErrorOutput()) {
            $output .= '<error>' . rtrim($errorOutput, PHP_EOL) . '</error>' . "\n";
        }

        return $output;

    }

    /**
     * Try that given options that define directories exists and are accessible.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param InputOption[] $dirs Option defining directories
     * @return bool
     */
    protected function testDirectories(InputInterface $input, OutputInterface $output, array $dirs)
    {
        /** @var $dirs InputOption[] */
        foreach ($dirs as $dir) {
            $currentValue = $input->getOption($dir->getName());

            if ($currentValue === false || realpath($currentValue) === false) {
                $output->writeln(sprintf(
                    '<error>%s does not exist, make sure it is accessible or define your own path using %s'
                    . ' option</error>',
                    $dir->getDescription(),
                    '--' . $dir->getName()
                ));

                return false;
            }
        }

        return true;
    }

    /**
     * Try connection to Selenium server
     * @param OutputInterface $output
     * @param string $seleniumServerUrl
     * @return bool
     */
    protected function testSeleniumConnection(OutputInterface $output, $seleniumServerUrl)
    {
        $seleniumAdapter = $this->getSeleniumAdapter();
        $output->write(sprintf('Selenium server (hub) url: %s, trying connection...', $seleniumServerUrl));

        if (!$seleniumAdapter->isAccessible($seleniumServerUrl)) {
            $output->writeln(sprintf('<error>connection error (%s)</error>', $seleniumAdapter->getLastError()));
            $output->writeln(
                sprintf(
                    '<error>Make sure your Selenium server is really accessible on url "%s" '
                    . 'or change it using --server-url option</error>',
                    $seleniumServerUrl
                )
            );

            return false;
        }

        if (!$seleniumAdapter->isSeleniumServer($seleniumServerUrl)) {
            $output->writeln(sprintf('<error>response error (%s)</error>', $seleniumAdapter->getLastError()));
            $output->writeln(
                sprintf(
                    '<error>Looks like url "%s" is occupied by something else than Selenium server. '
                    . 'Make Selenium server is really accessible on this url '
                    . 'or change it using --server-url option</error>',
                    $seleniumServerUrl
                )
            );

            return false;
        }

        $output->writeln('OK');

        return true;
    }
}
