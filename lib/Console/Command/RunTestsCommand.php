<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Console\Event\RunTestsProcessEvent;
use Lmc\Steward\Test\MaxTotalDelayStrategy;
use Lmc\Steward\Test\ProcessSet;
use Lmc\Steward\Publisher\XmlPublisher;
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
 *
 * @copyright LMC s.r.o.
 */
class RunTestsCommand extends Command
{
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

        $browsers = $input->getArgument('browser');
        $environment = $input->getArgument('environment');

        $pattern = $input->getOption('pattern');
        $testsDir = $input->getOption('tests-dir');
        $fixturesDir = $input->getOption('fixtures-dir');
        $logsDir = $input->getOption('logs-dir');
        $serverUrl = $input->getOption('server-url');
        $group = $input->getOption('group');
        $excludeGroup = $input->getOption('exclude-group');
        $publishResults = $input->getOption('publish-results');

        $output->writeln(sprintf('Browser: %s', $browsers));
        $output->writeln(sprintf('Environment: %s', $environment));

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
            $output->writeln(sprintf('Base path to fixtures results: %s', $fixturesDir));
            $output->writeln(sprintf('Path to logs: %s', $logsDir));
            $output->writeln(sprintf('Publish results: %s', ($publishResults) ? 'yes' : 'no'));
        }

        $this->getDispatcher()->dispatch(
            CommandEvents::RUN_TESTS_INIT,
            new ExtendedConsoleEvent($this, $input, $output)
        );

        if (!$this->testSeleniumConnection($output, $serverUrl)) {
            return 1;
        }

        $output->writeln('Searching for testcases:');
        if ($group) {
            $output->writeln(sprintf(' - in group(s): %s', implode(', ', $group)));
        }
        if ($excludeGroup) {
            $output->writeln(sprintf(' - excluding group(s): %s', implode(', ', $excludeGroup)));
        }
        $output->writeln(sprintf(' - in directory "%s"', $testsDir));
        $output->writeln(sprintf(' - by pattern "%s"', $pattern));

        $xmlPublisher = new XmlPublisher($environment, null, null);
        $xmlPublisher->setFileDir($logsDir);
        $xmlPublisher->clean();
        $processSet = new ProcessSet($xmlPublisher);

        $testCasesNum = 0;

        $files = (new Finder())->useBestAdapter()->files()->in($testsDir)->name($pattern);

        foreach ($files as $file) {
            $fileName = $file->getRealpath();
            // Parse classes from the testcase file
            $classes = AnnotationsParser::parsePhp(\file_get_contents($fileName));

            // Get annotations for the first class in testcase (one file = one class)
            $annotations = AnnotationsParser::getAll(new \ReflectionClass(key($classes)));

            // Filter out test-cases having any of excluded groups
            if ($excludeGroup && array_key_exists('group', $annotations)
                && count($excludingGroups = array_intersect($excludeGroup, $annotations['group']))
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
            if ($group) {
                if (!array_key_exists('group', $annotations)
                    || !count($matchingGroups = array_intersect($group, $annotations['group']))
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

            // If ANSI output is enabled, turn on colors on PHPUnit
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
                ->setEnv('BROWSER_NAME', $browsers)
                ->setEnv('ENV', strtolower($environment))
                ->setEnv('SERVER_URL', $serverUrl)
                ->setEnv('PUBLISH_RESULTS', $publishResults)
                ->setEnv('FIXTURES_DIR', $fixturesDir)
                ->setEnv('LOGS_DIR', $logsDir)
                ->setEnv('DEBUG', $output->isDebug())
                ->setPrefix(STEWARD_BASE_DIR . '/vendor/bin/phpunit')
                ->setArguments(array_merge($processEvent->getArgs(), [$fileName]))
                ->setTimeout(3600) // 1 hour timeout to end possibly stuck processes
                ->getProcess();

            $processSet->add(
                $process,
                key($classes),
                $delayAfter = !empty($annotations['delayAfter']) ? current($annotations['delayAfter']) : '',
                $delayMinutes = !empty($annotations['delayMinutes']) ? current($annotations['delayMinutes']) : 0
            );
        }

        if (!count($processSet)) {
            $output->writeln('No testcases matched given criteria, exiting.');

            return 1;
        }

        $processSet->optimizeOrder(new MaxTotalDelayStrategy());

        // Set tasks without delay as prepared in order to make them executed instantly
        $queuedProcesses = $processSet->get(ProcessSet::PROCESS_STATUS_QUEUED);
        foreach ($queuedProcesses as $className => $processObject) {
            if (!$processObject->delayMinutes) {
                if ($output->isDebug()) {
                    $output->writeln(sprintf('Testcase "%s" is prepared to be run', $className));
                }
                $processSet->setStatus($className, ProcessSet::PROCESS_STATUS_PREPARED);
            } else {
                if ($output->isDebug()) {
                    $output->writeln(
                        sprintf(
                            'Testcase "%s" is queued to be run %d minutes after testcase "%s" is finished',
                            $className,
                            $processObject->delayMinutes,
                            $processObject->delayAfter
                        )
                    );
                }
            }
        }

        $this->executionLoop($output, $processSet);
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
                    usleep(50000); // wait for a while (0,05 sec) to let processes be started in indented order

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
        $output->write(sprintf('Selenium server (hub) url: %s, trying connection...', $seleniumServerUrl));

        $urlParts = parse_url($seleniumServerUrl);

        // Check connection to server is possible
        $seleniumConnection = @fsockopen($urlParts['host'], $urlParts['port'], $connectionErrorNo, $connectionError, 5);
        if (!is_resource($seleniumConnection)) {
            $output->writeln(sprintf('<error>error (%s)</error>', $connectionError));
            $output->writeln(
                sprintf(
                    '<error>Make sure your Selenium server is really accessible on url "%s" '
                    . 'or change it using --server-url option</error>',
                    $seleniumServerUrl
                )
            );

            return false;
        }
        fclose($seleniumConnection);

        // Check server properly responds to http requests
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $responseData = @file_get_contents($seleniumServerUrl . '/wd/hub/status/', false, $context);

        if (!$responseData || !json_decode($responseData)) {
            $output->writeln('<error>error reading server response</error>');
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
