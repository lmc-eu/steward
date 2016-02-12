<?php

namespace Lmc\Steward\Console\Command;

use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Process\MaxTotalDelayStrategy;
use Lmc\Steward\Process\ProcessSet;
use Lmc\Steward\Process\ProcessSetCreator;
use Lmc\Steward\Publisher\XmlPublisher;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Finder\Finder;

/**
 * Run tests command is used to start Steward test planner and execute tests one by one,
 * optionally with defined delay and relations.
 */
class RunCommand extends Command
{
    /** @var SeleniumServerAdapter */
    protected $seleniumAdapter;
    /** @var ProcessSetCreator */
    protected $processSetCreator;
    /** @var array */
    protected $supportedBrowsers = [
        WebDriverBrowserType::FIREFOX,
        WebDriverBrowserType::CHROME,
        WebDriverBrowserType::IE,
        WebDriverBrowserType::SAFARI,
        WebDriverBrowserType::PHANTOMJS,
    ];

    const ARGUMENT_ENVIRONMENT = 'environment';
    const ARGUMENT_BROWSER = 'browser';
    const OPTION_SERVER_URL = 'server-url';
    const OPTION_TESTS_DIR = 'tests-dir';
    const OPTION_FIXTURES_DIR = 'fixtures-dir';
    const OPTION_LOGS_DIR = 'logs-dir';
    const OPTION_PATTERN = 'pattern';
    const OPTION_GROUP = 'group';
    const OPTION_EXCLUDE_GROUP = 'exclude-group';
    const OPTION_FILTER = 'filter';
    const OPTION_PUBLISH_RESULTS = 'publish-results';
    const OPTION_NO_EXIT = 'no-exit';

    /**
     * @param SeleniumServerAdapter $seleniumAdapter
     */
    public function setSeleniumAdapter(SeleniumServerAdapter $seleniumAdapter)
    {
        $this->seleniumAdapter = $seleniumAdapter;
    }

    /**
     * @param ProcessSetCreator $processSetCreator
     */
    public function setProcessSetCreator(ProcessSetCreator $processSetCreator)
    {
        $this->processSetCreator = $processSetCreator;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('run')
            ->setAliases(['run-tests'])
            ->setDescription('Run tests planner and execute tests')
            ->addArgument(
                self::ARGUMENT_ENVIRONMENT,
                InputArgument::REQUIRED,
                'Environment name (must be specified to avoid unintentional run against production)'
            )
            ->addArgument(
                self::ARGUMENT_BROWSER,
                InputArgument::REQUIRED,
                'Browser in which tests should be run'
            )
            ->addOption(
                self::OPTION_SERVER_URL,
                null,
                InputOption::VALUE_REQUIRED,
                'Selenium server (hub) hub hostname and port',
                'http://localhost:4444'
            )
            ->addOption(
                self::OPTION_TESTS_DIR,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to directory with tests',
                realpath(STEWARD_BASE_DIR . '/tests')
            )
            ->addOption(
                self::OPTION_FIXTURES_DIR,
                null,
                InputOption::VALUE_REQUIRED,
                'Base path to directory with fixture files',
                realpath(STEWARD_BASE_DIR . '/tests')
            )->addOption(
                self::OPTION_LOGS_DIR,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to directory with logs',
                realpath(STEWARD_BASE_DIR . '/logs')
            )
            ->addOption(
                self::OPTION_PATTERN,
                null,
                InputOption::VALUE_REQUIRED,
                'Pattern for test files to be run',
                '*Test.php'
            )
            ->addOption(
                self::OPTION_GROUP,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Only run testcases with specified @group of this name'
            )
            ->addOption(
                self::OPTION_EXCLUDE_GROUP,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Exclude testcases with specified @group from being run'
            )
            ->addOption(
                self::OPTION_FILTER,
                null,
                InputOption::VALUE_REQUIRED,
                'Run only testcases/tests with name matching this filter'
            )
            ->addOption(
                self::OPTION_PUBLISH_RESULTS,
                null,
                InputOption::VALUE_NONE,
                'Publish test results to test storage'
            )
            ->addOption(
                self::OPTION_NO_EXIT,
                null,
                InputOption::VALUE_NONE,
                'Always exit with code 0 <comment>(by default any failed test causes the command to return 1)</comment>'
            );

        $this->addUsage('staging firefox');
        $this->addUsage('--group=foo --group=bar --exclude-group=baz -vvv development phantomjs');

        $this->getDispatcher()->dispatch(CommandEvents::CONFIGURE, new BasicConsoleEvent($this));
    }

    /**
     * Initialize, check arguments and options values etc.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $output->writeln(
            sprintf(
                '<info>Steward</info> <comment>%s</comment> is running the tests...%s',
                $this->getApplication()->getVersion(),
                (!$this->isCi() ? ' Just for you <fg=red><3</fg=red>!' : '') // on CI server it is not just for you
            )
        );

        // If browser name or env is empty, ends initialization and let the Console/Command fail on input validation
        if (empty($input->getArgument(self::ARGUMENT_BROWSER))
            || empty($input->getArgument(self::ARGUMENT_ENVIRONMENT))
        ) {
            return;
        }

        // Browser name is case insensitive, normalize it to lower case
        $input->setArgument(self::ARGUMENT_BROWSER, strtolower($input->getArgument(self::ARGUMENT_BROWSER)));
        $browser = $input->getArgument(self::ARGUMENT_BROWSER);

        // Check if browser is supported
        if (!in_array($browser, $this->supportedBrowsers)) {
            throw new \RuntimeException(
                sprintf(
                    'Browser "%s" is not supported (use one of: %s)',
                    $browser,
                    implode(', ', $this->supportedBrowsers)
                )
            );
        }

        if ($output->isVerbose()) {
            $output->writeln(sprintf('Browser: %s', $browser));
            $output->writeln(sprintf('Environment: %s', $input->getArgument(self::ARGUMENT_ENVIRONMENT)));
        }

        // Check if directories exists
        $this->testDirectories(
            $input,
            $output,
            [
                $this->getDefinition()->getOption(self::OPTION_TESTS_DIR),
                $this->getDefinition()->getOption(self::OPTION_LOGS_DIR),
                $this->getDefinition()->getOption(self::OPTION_FIXTURES_DIR),
            ]
        );

        if ($output->isVeryVerbose()) {
            $output->writeln(
                sprintf('Base path to fixtures results: %s', $input->getOption(self::OPTION_FIXTURES_DIR))
            );
            $output->writeln(
                sprintf('Path to logs: %s', $input->getOption(self::OPTION_LOGS_DIR))
            );
            $output->writeln(
                sprintf('Publish results: %s', ($input->getOption(self::OPTION_PUBLISH_RESULTS)) ? 'yes' : 'no')
            );
        }
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
        $this->getDispatcher()->dispatch(
            CommandEvents::RUN_TESTS_INIT,
            new ExtendedConsoleEvent($this, $input, $output)
        );

        if (!$this->testSeleniumConnection($output, $input->getOption(self::OPTION_SERVER_URL))) {
            return 1;
        }

        // Find all files holding test-cases
        if ($output->isVeryVerbose()) {
            $output->writeln('Searching for testcases:');
            $output->writeln(sprintf(' - in directory "%s"', $input->getOption(self::OPTION_TESTS_DIR)));
            $output->writeln(sprintf(' - by pattern "%s"', $input->getOption(self::OPTION_PATTERN)));
        }

        $files = (new Finder())
            ->files()
            ->in($input->getOption(self::OPTION_TESTS_DIR))
            ->name($input->getOption(self::OPTION_PATTERN));

        if (!count($files)) {
            $output->writeln(
                sprintf(
                    '<error>No testcases found, exiting.%s</error>',
                    !$output->isVeryVerbose() ? ' (use -vv or -vvv option for more information)' : ''
                )
            );

            return 1;
        }

        $processSetCreator = $this->getProcessSetCreator($input, $output);
        $processSet = $processSetCreator->createFromFiles(
            $files,
            $input->getOption(self::OPTION_GROUP),
            $input->getOption(self::OPTION_EXCLUDE_GROUP),
            $input->getOption(self::OPTION_FILTER)
        );

        if (!count($processSet)) {
            $output->writeln('<error>No testcases matched given groups, exiting.</error>');

            return 1;
        }

        // Optimize processes order
        $processSet->optimizeOrder(new MaxTotalDelayStrategy());

        // Initialize first processes that should be run
        $processSet->dequeueProcessesWithoutDelay($output);

        // Start execution loop
        $output->writeln('');
        $allTestsPassed = $this->executionLoop($output, $processSet);

        if ($input->getOption(self::OPTION_NO_EXIT)) {
            return 0;
        } else {
            return $allTestsPassed ? 0 : 1;
        }
    }

    /**
     * @codeCoverageIgnore
     * @return SeleniumServerAdapter
     */
    protected function getSeleniumAdapter()
    {
        if (!$this->seleniumAdapter) {
            $this->seleniumAdapter = new SeleniumServerAdapter();
        }

        return $this->seleniumAdapter;
    }

    /**
     * @codeCoverageIgnore
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return ProcessSetCreator
     */
    protected function getProcessSetCreator(InputInterface $input, OutputInterface $output)
    {
        if (!$this->processSetCreator) {
            $xmlPublisher = new XmlPublisher($input->getArgument(self::ARGUMENT_ENVIRONMENT), null, null);
            $xmlPublisher->setFileDir($input->getOption(self::OPTION_LOGS_DIR));
            $xmlPublisher->clean();

            $this->processSetCreator = new ProcessSetCreator($this, $input, $output, $xmlPublisher);
        }

        return $this->processSetCreator;
    }

    /**
     * Start planner execution loop
     *
     * @param OutputInterface $output
     * @param ProcessSet $processSet
     * @return bool Return true if all test returned exit code 0 (or if none test was run)
     */
    protected function executionLoop(OutputInterface $output, ProcessSet $processSet)
    {
        $counterWaitingOutput = 1;
        $statusesCountLast = [];
        // Iterate over prepared and queued until everything is done
        while (true) {
            $prepared = $processSet->get(ProcessSet::PROCESS_STATUS_PREPARED);
            $queued = $processSet->get(ProcessSet::PROCESS_STATUS_QUEUED);

            if (count($prepared) == 0 && count($queued) == 0) {
                break;
            }

            // Start all prepared tasks and set status of not running as finished
            foreach ($prepared as $testClass => $processWrapper) {
                if (!$processWrapper->process->isStarted()) {
                    $output->writeln(
                        sprintf(
                            'Execution of testcase "%s" started%s',
                            $testClass,
                            $output->isDebug() ? " with command:\n" . $processWrapper->process->getCommandLine() : ''
                        ),
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                    $processWrapper->process->start();
                    usleep(50000); // wait for a while (0,05 sec) to let processes be started in intended order

                    continue;
                }

                $timeoutError = $this->checkProcessTimeout($processSet, $processWrapper, $testClass);
                if ($timeoutError) {
                    $output->writeln('<error>' . $timeoutError . '</error>', OutputInterface::VERBOSITY_VERY_VERBOSE);
                }

                if ($output->isDebug()) { // In debug mode print all output as it comes
                    $processOutput = $this->getProcessOutput($processWrapper->process);
                    if ($processOutput) {
                        $output->write($processOutput);
                    }
                }

                if (!$processWrapper->process->isRunning()) {
                    // Mark no longer running processes as finished
                    $processSet->setStatus($testClass, ProcessSet::PROCESS_STATUS_DONE);
                    $processWrapper->finishedTime = time();
                    $hasProcessPassed = $processWrapper->result == ProcessSet::PROCESS_RESULT_PASSED;

                    if ($output->isVeryVerbose()) {
                        $processOutput = '';
                        if (!$hasProcessPassed) {
                            $processOutput = $this->getProcessOutput($processWrapper->process);
                        }

                        $output->writeln(
                            sprintf(
                                '<fg=%s>Finished execution of testcase "%s" (result: %s)%s</>',
                                $hasProcessPassed ? 'green' : 'red',
                                $testClass,
                                $processWrapper->result,
                                $processOutput ? ', output:' : ''
                            )
                        );

                        if ($processOutput) { // Eg. in debug mode the output was already printed
                            $output->write($processOutput);
                        }
                    } elseif ($output->isVerbose() && !$hasProcessPassed) {
                        $output->writeln(sprintf('<fg=red>Testcase "%s" %s</>', $testClass, $processWrapper->result));
                    }
                }
            }

            $done = $processSet->get(ProcessSet::PROCESS_STATUS_DONE);
            $doneClasses = [];
            // Retrieve names of done tests
            foreach ($done as $testClass => $processWrapper) {
                $doneClasses[] = $testClass;
            }
            // Set queued tasks as prepared if their dependent task is done and delay has passed
            foreach ($queued as $testClass => $processWrapper) {
                $delaySeconds = $processWrapper->delayMinutes * 60;

                if (in_array($processWrapper->delayAfter, $doneClasses)
                    && (time() - $done[$processWrapper->delayAfter]->finishedTime) > $delaySeconds
                ) {
                    $output->writeln(
                        sprintf('Unqueing testcase "%s"', $testClass),
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                    $processSet->setStatus($testClass, ProcessSet::PROCESS_STATUS_PREPARED);
                }
            }

            $statusesCount = $processSet->countStatuses();
            // if the output didn't change, wait 10 seconds before printing it again
            if ($statusesCount === $statusesCountLast && $counterWaitingOutput % 10 !== 0) {
                $counterWaitingOutput++;
            } else {
                // prepare information about results of finished processes
                $resultsInfo = [];
                $resultsCount = $processSet->countResults();
                if ($statusesCount[ProcessSet::PROCESS_STATUS_DONE] > 0) {
                    foreach (ProcessSet::$processResults as $resultType) {
                        if ($resultsCount[$resultType] > 0) {
                            $resultsInfo[] = sprintf(
                                '%s: <fg=%s>%d</>',
                                $resultType,
                                $resultType == ProcessSet::PROCESS_RESULT_PASSED ? 'green' : 'red',
                                $resultsCount[$resultType]
                            );
                        }
                    }
                }

                $output->writeln(
                    sprintf(
                        "[%s]: waiting (running: %d, queued: %d, done: %d%s)",
                        date("Y-m-d H:i:s"),
                        $statusesCount[ProcessSet::PROCESS_STATUS_PREPARED],
                        $statusesCount[ProcessSet::PROCESS_STATUS_QUEUED],
                        $statusesCount[ProcessSet::PROCESS_STATUS_DONE],
                        count($resultsInfo) ? ' [' . implode(', ', $resultsInfo) . ']' : ''
                    )
                );
                $counterWaitingOutput = 1;
            }
            $statusesCountLast = $statusesCount;
            sleep(1);
        }

        $doneCount = count($processSet->get(ProcessSet::PROCESS_STATUS_DONE));
        $resultsCount = $processSet->countResults();
        $allTestsPassed = ($resultsCount[ProcessSet::PROCESS_RESULT_PASSED] == $doneCount);
        $resultsInfo = [];
        foreach (ProcessSet::$processResults as $resultType) {
            if ($resultsCount[$resultType] > 0) {
                $resultsInfo[] = sprintf('%s: %d', $resultType, $resultsCount[$resultType]);
            }
        }

        $output->writeln(
            sprintf(
                "\n<%s>Testcases executed: %d (%s)</>",
                $allTestsPassed ? 'fg=black;bg=green' : 'error',
                $doneCount,
                implode(', ', $resultsInfo)
            )
        );

        return $allTestsPassed;
    }

    /**
     * Check if process is not running longer then specified timeout, return error message if so.
     * @param ProcessSet $processSet
     * @param array $processWrapper Wrapper of process instance
     * @param string $testClass Name of tested class
     * @return null|string Error message if process timeout exceeded
     */
    protected function checkProcessTimeout(ProcessSet $processSet, $processWrapper, $testClass)
    {
        try {
            $processWrapper->process->checkTimeout();
        } catch (ProcessTimedOutException $e) {
            $processSet->setStatus($testClass, ProcessSet::PROCESS_STATUS_DONE);
            return sprintf(
                '[%s]: Process for class "%s" exceeded the timeout of %d seconds and was killed.',
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
     * @throws \RuntimeException Thrown when directory is not accessible
     */
    protected function testDirectories(InputInterface $input, OutputInterface $output, array $dirs)
    {
        /** @var $dirs InputOption[] */
        foreach ($dirs as $dir) {
            $currentValue = $input->getOption($dir->getName());

            if ($currentValue === false || realpath($currentValue) === false) {
                throw new \RuntimeException(
                    sprintf(
                        '%s does not exist, make sure it is accessible or define your own path using %s option',
                        $dir->getDescription(),
                        '--' . $dir->getName()
                    )
                );
            }
        }
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
        $output->write(
            sprintf('Selenium server (hub) url: %s, trying connection...', $seleniumServerUrl),
            false,
            OutputInterface::VERBOSITY_VERY_VERBOSE
        );

        if (!$seleniumAdapter->isAccessible($seleniumServerUrl)) {
            $output->writeln(
                sprintf(
                    '<error>%s ("%s")</error>',
                    $output->isVeryVerbose() ? 'connection error' : 'Error connecting to Selenium server',
                    $seleniumAdapter->getLastError()
                )
            );
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
            $output->writeln(
                sprintf(
                    '<error>%s (%s)</error>',
                    $output->isVeryVerbose() ? 'unexpected response' : 'Unexpected response from Selenium server',
                    $seleniumAdapter->getLastError()
                )
            );
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

        $output->writeln('OK', OutputInterface::VERBOSITY_VERY_VERBOSE);

        return true;
    }
}
