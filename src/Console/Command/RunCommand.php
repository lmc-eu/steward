<?php

namespace Lmc\Steward\Console\Command;

use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Console\Style\StewardStyle;
use Lmc\Steward\Process\MaxTotalDelayStrategy;
use Lmc\Steward\Process\ProcessSet;
use Lmc\Steward\Process\ProcessSetCreator;
use Lmc\Steward\Process\ProcessWrapper;
use Lmc\Steward\Publisher\XmlPublisher;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use OndraM\CiDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

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
    const OPTION_CAPABILITY = 'capability';
    const OPTION_TESTS_DIR = 'tests-dir';
    const OPTION_FIXTURES_DIR = 'fixtures-dir';
    const OPTION_LOGS_DIR = 'logs-dir';
    const OPTION_PATTERN = 'pattern';
    const OPTION_GROUP = 'group';
    const OPTION_EXCLUDE_GROUP = 'exclude-group';
    const OPTION_FILTER = 'filter';
    const OPTION_NO_EXIT = 'no-exit';
    const OPTION_IGNORE_DELAYS = 'ignore-delays';

    /**
     * @internal
     * @param SeleniumServerAdapter $seleniumAdapter
     */
    public function setSeleniumAdapter(SeleniumServerAdapter $seleniumAdapter)
    {
        $this->seleniumAdapter = $seleniumAdapter;
    }

    /**
     * @internal
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
                'Selenium server (hub) URL (may include port numbe)',
                'http://localhost:4444'
            )
            ->addOption(
                self::OPTION_CAPABILITY,
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Extra DesiredCapabilities to be passed to WebDriver, use format capabilityName:value'
            )
            ->addOption(
                self::OPTION_TESTS_DIR,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to directory with tests',
                STEWARD_BASE_DIR . '/tests'
            )
            ->addOption(
                self::OPTION_FIXTURES_DIR,
                null,
                InputOption::VALUE_REQUIRED,
                'Base path to directory with fixture files',
                STEWARD_BASE_DIR . '/tests'
            )
            ->addOption(
                self::OPTION_LOGS_DIR,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to directory with logs',
                STEWARD_BASE_DIR . '/logs'
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
                self::OPTION_NO_EXIT,
                null,
                InputOption::VALUE_NONE,
                'Always exit with code 0 <comment>(by default any failed test causes the command to return 1)</comment>'
            )
            ->addOption(
                self::OPTION_IGNORE_DELAYS,
                'i',
                InputOption::VALUE_NONE,
                'Ignore delays defined between testcases'
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
                (!CiDetector::detect() ? ' Just for you <fg=red><3</fg=red>!' : '') // on CI it is not just for you...
            )
        );

        // If browser name or env is empty, ends initialization and let the Console/Command fail on input validation
        if (empty($input->getArgument(self::ARGUMENT_BROWSER))
            || empty($input->getArgument(self::ARGUMENT_ENVIRONMENT))
        ) {
            return;
        }

        // Browser name is case insensitive, normalize it to lower case
        $input->setArgument(self::ARGUMENT_BROWSER, mb_strtolower($input->getArgument(self::ARGUMENT_BROWSER)));
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

        $this->getDispatcher()->dispatch(
            CommandEvents::RUN_TESTS_INIT,
            new ExtendedConsoleEvent($this, $input, $output)
        );

        // Check if directories exists
        $this->testDirectories(
            $input,
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
                sprintf('Ignore delays: %s', ($input->getOption(self::OPTION_IGNORE_DELAYS)) ? 'yes' : 'no')
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
        $io = new StewardStyle($input, $output);

        if (!$this->testSeleniumConnection($io, $input->getOption(self::OPTION_SERVER_URL))) {
            return 1;
        }

        // Find all files holding test-cases
        if ($io->isVeryVerbose()) {
            $io->writeln('Searching for testcases:');
            $io->writeln(sprintf(' - in directory "%s"', $input->getOption(self::OPTION_TESTS_DIR)));
            $io->writeln(sprintf(' - by pattern "%s"', $input->getOption(self::OPTION_PATTERN)));
        }

        $files = (new Finder())
            ->files()
            ->in($input->getOption(self::OPTION_TESTS_DIR))
            ->name($input->getOption(self::OPTION_PATTERN));

        if (!count($files)) {
            $io->error(
                sprintf(
                    'No testcases found, exiting.%s',
                    !$io->isVeryVerbose() ? ' (use -vv or -vvv option for more information)' : ''
                )
            );

            return 1;
        }

        $processSetCreator = $this->getProcessSetCreator($input, $io);
        $processSet = $processSetCreator->createFromFiles(
            $files,
            $input->getOption(self::OPTION_GROUP),
            $input->getOption(self::OPTION_EXCLUDE_GROUP),
            $input->getOption(self::OPTION_FILTER),
            $input->getOption(self::OPTION_IGNORE_DELAYS)
        );

        if (!count($processSet)) {
            $io->error('No testcases matched given groups, exiting.');

            return 1;
        }

        // Optimize processes order
        $processSet->optimizeOrder(new MaxTotalDelayStrategy());

        // Initialize first processes that should be run
        $processSet->dequeueProcessesWithoutDelay($io);

        // Start execution loop
        $io->isVeryVerbose() ? $io->section('Starting execution of testcases') : $io->newLine();
        $allTestsPassed = $this->executionLoop($io, $processSet);

        if ($input->getOption(self::OPTION_NO_EXIT)) {
            return 0;
        }

        return $allTestsPassed ? 0 : 1;
    }

    /**
     * @codeCoverageIgnore
     * @param string $seleniumServerUrl
     * @return SeleniumServerAdapter
     */
    protected function getSeleniumAdapter($seleniumServerUrl)
    {
        if (!$this->seleniumAdapter) {
            $this->seleniumAdapter = new SeleniumServerAdapter($seleniumServerUrl);
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
            $xmlPublisher = new XmlPublisher();
            $xmlPublisher->setFileDir($input->getOption(self::OPTION_LOGS_DIR));
            $xmlPublisher->clean();

            $this->processSetCreator = new ProcessSetCreator($this, $input, $output, $xmlPublisher);
        }

        return $this->processSetCreator;
    }

    /**
     * Start planner execution loop
     *
     * @param StewardStyle $io
     * @param ProcessSet $processSet
     * @return bool Return true if all test returned exit code 0 (or if none test was run)
     */
    protected function executionLoop(StewardStyle $io, ProcessSet $processSet)
    {
        $counterWaitingOutput = 1;
        $statusesCountLast = [];
        // Iterate over prepared and queued until everything is done
        while (true) {
            $prepared = $processSet->get(ProcessWrapper::PROCESS_STATUS_PREPARED);
            $queued = $processSet->get(ProcessWrapper::PROCESS_STATUS_QUEUED);

            if (count($prepared) == 0 && count($queued) == 0) {
                break;
            }

            // Start all prepared tasks and set status of not running as finished
            foreach ($prepared as $testClass => $processWrapper) {
                if (!$processWrapper->getProcess()->isStarted()) {
                    if ($io->isVeryVerbose()) {
                        $io->runStatus(
                            sprintf(
                                'Execution of testcase "%s" started%s',
                                $testClass,
                                $io->isDebug() ?
                                    " with command:\n" . $processWrapper->getProcess()->getCommandLine() : ''
                            )
                        );
                    }
                    $processWrapper->getProcess()->start();
                    usleep(50000); // wait for a while (0,05 sec) to let processes be started in intended order

                    continue;
                }

                if ($timeoutError = $processWrapper->checkProcessTimeout()) {
                    if ($io->isVeryVerbose()) {
                        $io->error($timeoutError);
                    }
                }

                if ($io->isDebug()) { // In debug mode print all output as it comes
                    $io->output(
                        $processWrapper->getProcess()->getIncrementalOutput(),
                        $processWrapper->getClassName()
                    );
                    $io->errorOutput(
                        $processWrapper->getProcess()->getIncrementalErrorOutput(),
                        $processWrapper->getClassName()
                    );
                }

                if (!$processWrapper->getProcess()->isRunning()) {
                    // Mark no longer running processes as finished
                    $processWrapper->setStatus(ProcessWrapper::PROCESS_STATUS_DONE);

                    $hasProcessPassed = $processWrapper->getResult() == ProcessWrapper::PROCESS_RESULT_PASSED;

                    if ($io->isVeryVerbose()) {
                        $processOutput = $processErrorOutput = '';
                        if (!$hasProcessPassed) { // If process failed, collect its output
                            $processOutput = $processWrapper->getProcess()->getIncrementalOutput();
                            $processErrorOutput = $processWrapper->getProcess()->getIncrementalErrorOutput();
                        }

                        $testcaseFinishedMessage = sprintf(
                            'Finished execution of testcase "%s" (result: %s)%s',
                            $testClass,
                            $processWrapper->getResult(),
                            (!empty($processOutput) || !empty($processErrorOutput) ? ', output:' : '')
                        );
                        $hasProcessPassed ? $io->runStatusSuccess($testcaseFinishedMessage)
                            : $io->runStatusError($testcaseFinishedMessage);

                        $io->output($processOutput, $processWrapper->getClassName());
                        $io->errorOutput($processErrorOutput, $processWrapper->getClassName());
                    } elseif ($io->isVerbose() && !$hasProcessPassed) {
                        $io->runStatusError(sprintf('Testcase "%s" %s', $testClass, $processWrapper->getResult()));
                    }

                    // Fail also process dependencies
                    if (!$hasProcessPassed) {
                        $this->failDependants($io, $processSet, $testClass);
                    }
                }
            }

            $this->unqueueDependentProcesses($io, $processSet);

            $statusesCount = $processSet->countStatuses();

            // if the output didn't change, wait 100 iterations (10 seconds) before printing it again
            if ($statusesCount === $statusesCountLast && $counterWaitingOutput % 100 !== 0) {
                $counterWaitingOutput++;
            } else {
                $this->printExecutionLoopStatus($io, $processSet, $statusesCount);
                $counterWaitingOutput = 1;
            }

            $statusesCountLast = $statusesCount;
            usleep(100000); // 0,1 sec
        }

        $doneCount = count($processSet->get(ProcessWrapper::PROCESS_STATUS_DONE));
        $resultsCount = $processSet->countResults();
        $allTestsPassed = ($resultsCount[ProcessWrapper::PROCESS_RESULT_PASSED] == $doneCount);
        $resultsInfo = [];
        foreach (ProcessWrapper::$processResults as $resultType) {
            if ($resultsCount[$resultType] > 0) {
                $resultsInfo[] = sprintf('%s: %d', $resultType, $resultsCount[$resultType]);
            }
        }

        $io->runStatus('All testcases done');

        $resultMessage = sprintf('Testcases executed: %d (%s)', $doneCount, implode(', ', $resultsInfo));
        $allTestsPassed ? $io->success($resultMessage) : $io->error($resultMessage);

        return $allTestsPassed;
    }

    /**
     * Try that given options that define directories exists and are accessible.
     *
     * @param InputInterface $input
     * @param InputOption[] $dirs Option defining directories
     * @throws \RuntimeException Thrown when directory is not accessible
     */
    protected function testDirectories(InputInterface $input, array $dirs)
    {
        /** @var $dirs InputOption[] */
        foreach ($dirs as $dir) {
            $currentValue = $input->getOption($dir->getName());

            if (realpath($currentValue) === false || !is_readable($currentValue)) {
                throw new \RuntimeException(
                    sprintf(
                        '%s "%s" does not exist, make sure it is accessible or define your own path using %s option',
                        $dir->getDescription(),
                        $currentValue,
                        '--' . $dir->getName()
                    )
                );
            }
        }
    }

    /**
     * Try connection to Selenium server
     * @param StewardStyle $io
     * @param string $seleniumServerUrl
     * @return bool
     */
    protected function testSeleniumConnection(StewardStyle $io, $seleniumServerUrl)
    {
        $seleniumAdapter = $this->getSeleniumAdapter($seleniumServerUrl);
        $io->write(
            sprintf('Selenium server (hub) url: %s, trying connection...', $seleniumAdapter->getServerUrl()),
            false,
            OutputInterface::VERBOSITY_VERY_VERBOSE
        );

        if (!$seleniumAdapter->isAccessible()) {
            $io->writeln(
                sprintf(
                    '<error>%s ("%s")</error>',
                    $io->isVeryVerbose() ? 'connection error' : 'Error connecting to Selenium server',
                    $seleniumAdapter->getLastError()
                )
            );

            $io->error(
                sprintf(
                    'Make sure your Selenium server is really accessible on url "%s" '
                    . 'or change it using --server-url option',
                    $seleniumAdapter->getServerUrl()
                )
            );

            return false;
        }

        if (!$seleniumAdapter->isSeleniumServer()) {
            $io->writeln(
                sprintf(
                    '<error>%s (%s)</error>',
                    $io->isVeryVerbose() ? 'unexpected response' : 'Unexpected response from Selenium server',
                    $seleniumAdapter->getLastError()
                )
            );
            $io->error(
                sprintf(
                    'Looks like url "%s" is occupied by something else than Selenium server. '
                    . 'Make sure Selenium server is really accessible on this url '
                    . 'or change it using --server-url option',
                    $seleniumAdapter->getServerUrl()
                )
            );

            return false;
        }

        if ($io->isVeryVerbose()) {
            $cloudService = $seleniumAdapter->getCloudService();
            $io->writeln(
                'OK' . ($cloudService ? ' (' . $cloudService . ' cloud service detected)' : ''),
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );
        }

        return true;
    }

    /**
     * @param StewardStyle $io
     * @param ProcessSet $processSet
     * @param $testClass
     */
    protected function failDependants(StewardStyle $io, ProcessSet $processSet, $testClass)
    {
        $failedDependants = $processSet->failDependants($testClass);

        if ($io->isVerbose()) {
            foreach ($failedDependants as $failedClass => $failedProcessWrapper) {
                $io->runStatusError(
                    sprintf(
                        'Failing testcase "%s", because it was depending on failed "%s"',
                        $failedClass,
                        $testClass
                    )
                );
            }
        }
    }

    /**
     * @param StewardStyle $io
     * @param ProcessSet $processSet
     * @return array
     */
    protected function unqueueDependentProcesses(StewardStyle $io, ProcessSet $processSet)
    {
        // Retrieve names of done tests
        $done = $processSet->get(ProcessWrapper::PROCESS_STATUS_DONE);
        $doneClasses = [];
        foreach ($done as $testClass => $processWrapper) {
            $doneClasses[] = $testClass;
        }

        // Set queued tasks as prepared if their dependent task is done and delay has passed
        $queued = $processSet->get(ProcessWrapper::PROCESS_STATUS_QUEUED);
        foreach ($queued as $testClass => $processWrapper) {
            $delaySeconds = $processWrapper->getDelayMinutes() * 60;

            if (in_array($processWrapper->getDelayAfter(), $doneClasses)
                && (time() - $done[$processWrapper->getDelayAfter()]->getFinishedTime()) > $delaySeconds
            ) {
                if ($io->isVeryVerbose()) {
                    $io->runStatus(sprintf('Unqueing testcase "%s"', $testClass));
                }
                $processWrapper->setStatus(ProcessWrapper::PROCESS_STATUS_PREPARED);
            }
        }
    }

    /**
     * @param StewardStyle $io
     * @param ProcessSet $processSet
     * @param $statusesCount
     * @return array
     */
    protected function printExecutionLoopStatus(StewardStyle $io, ProcessSet $processSet, $statusesCount)
    {
        $resultsInfo = [];
        $resultsCount = $processSet->countResults();
        if ($statusesCount[ProcessWrapper::PROCESS_STATUS_DONE] > 0) {
            foreach (ProcessWrapper::$processResults as $resultType) {
                if ($resultsCount[$resultType] > 0) {
                    $resultsInfo[] = sprintf(
                        '%s: <fg=%s>%d</>',
                        $resultType,
                        $resultType == ProcessWrapper::PROCESS_RESULT_PASSED ? 'green' : 'red',
                        $resultsCount[$resultType]
                    );
                }
            }
        }

        $io->runStatus(
            sprintf(
                'Waiting (running: %d, queued: %d, done: %d%s)',
                $statusesCount[ProcessWrapper::PROCESS_STATUS_PREPARED],
                $statusesCount[ProcessWrapper::PROCESS_STATUS_QUEUED],
                $statusesCount[ProcessWrapper::PROCESS_STATUS_DONE],
                count($resultsInfo) ? ' [' . implode(', ', $resultsInfo) . ']' : ''
            )
        );
    }
}
