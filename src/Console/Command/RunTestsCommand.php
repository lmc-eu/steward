<?php

namespace Lmc\Steward\Console\Command;

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
class RunTestsCommand extends Command
{
    /** @var SeleniumServerAdapter */
    protected $seleniumAdapter;
    /** @var ProcessSetCreator */
    protected $processSetCreator;
    /** @var array */
    protected $supportedBrowsers = [
        \WebDriverBrowserType::FIREFOX,
        \WebDriverBrowserType::CHROME,
        \WebDriverBrowserType::IE,
        \WebDriverBrowserType::SAFARI,
        \WebDriverBrowserType::PHANTOMJS,
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
    const OPTION_PUBLISH_RESULTS = 'publish-results';

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
        $this->setName('run-tests')
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
                self::OPTION_PUBLISH_RESULTS,
                null,
                InputOption::VALUE_NONE,
                'Publish test results to test storage'
            );

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
                (!getenv('JOB_NAME') ? ' Just for you <fg=red><3</fg=red>!' : '') // in jenkins it is not just for you
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

        $output->writeln(sprintf('Browser: %s', $browser));
        $output->writeln(sprintf('Environment: %s', $input->getArgument(self::ARGUMENT_ENVIRONMENT)));

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

        if ($output->isDebug()) {
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
        $output->writeln('Searching for testcases:');
        $output->writeln(sprintf(' - in directory "%s"', $input->getOption(self::OPTION_TESTS_DIR)));
        $output->writeln(sprintf(' - by pattern "%s"', $input->getOption(self::OPTION_PATTERN)));

        $files = (new Finder())
            ->useBestAdapter()
            ->files()
            ->in($input->getOption(self::OPTION_TESTS_DIR))
            ->name($input->getOption(self::OPTION_PATTERN));

        if (!count($files)) {
            $output->writeln('No testcases found, exiting.');

            return 1;
        }

        $processSetCreator = $this->getProcessSetCreator($input, $output);
        $processSet = $processSetCreator->createFromFiles(
            $files,
            $input->getOption(self::OPTION_GROUP),
            $input->getOption(self::OPTION_EXCLUDE_GROUP)
        );

        if (!count($processSet)) {
            $output->writeln('No testcases matched given groups, exiting.');

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
