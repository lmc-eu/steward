<?php

namespace Lmc\Steward\Command;

use Lmc\Steward\Test\ProcessSet;
use Lmc\Steward\Publisher\XmlPublisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\Process;
use Nette\Utils\Finder;
use Nette\Reflection\AnnotationsParser;
use Nette\Utils\Strings;

/**
 * Run tests command is used to start Steward test planner and execute tests one by one,
 * optionaly with defined delay.
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
                'dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to directory with tests',
                realpath(__DIR__ . '/../../tests')
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
                InputOption::VALUE_REQUIRED,
                'Only runs testcases with specified @group of this name'
            )
            ->addOption(
                'publish-results',
                null,
                InputOption::VALUE_NONE,
                'Publish test results to test storage'
            );
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
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
        $dir = $input->getOption('dir');
        $serverUrl = $input->getOption('server-url');
        $parsedUrl = parse_url($serverUrl);
        $group = $input->getOption('group');
        $publishResults = $input->getOption('publish-results');

        $output->writeln(sprintf('Browser: %s', $browsers));
        $output->writeln(sprintf('Environment: %s', $environment));

        $output->writeln(sprintf('Publish results: %s', ($publishResults) ? 'yes' : 'no'));

        $output->write(sprintf('Selenium server (hub) url: %s, trying connection...', $serverUrl));

        // Try connection
        $seleniumConnection = @fsockopen($parsedUrl['host'], $parsedUrl['port'], $connectionErrorNo, $connectionError);
        if (!is_resource($seleniumConnection)) {
            $output->writeln(sprintf('error (%s)', $connectionError));

            return 1;
        }
        $output->writeln('OK');

        $output->writeln('Searching for testcases:');
        if ($group) {
            $output->writeln(sprintf(' - in group "%s"', $group));
        }
        $output->writeln(sprintf(' - in directory "%s"', $dir));
        $output->writeln(sprintf(' - by pattern "%s"', $pattern));

        $xmlPublisher = new XmlPublisher($environment, null, null);
        $xmlPublisher->clean();
        $processSet = new ProcessSet($xmlPublisher);

        $testCasesNum = 0;
        foreach (Finder::findFiles($pattern)->from($dir) as $fileName => $fileObject) {
            // Parse classes from the testcase file
            $classes = AnnotationsParser::parsePhp(\file_get_contents($fileName));

            // Get annotations for the first class in testcase (one file = one class)
            $annotations = AnnotationsParser::getAll(new \ReflectionClass(key($classes)));

            // If group is specified, but the class does not have it, skip the test now
            if ($group) {
                if (!array_key_exists('group', $annotations) || !in_array($group, $annotations['group'])) {
                    continue;
                }
                if ($output->isDebug()) {
                    $output->writeln(
                        sprintf(
                            'Found testcase file #%d in group %s: %s',
                            ++$testCasesNum,
                            $group,
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
                '--configuration=lib/phpunit.xml',
            ];

            // If ANSI output is enabled, turn on colors on PHPUnit
            if ($output->isDecorated()) {
                $phpunitArgs[] = '--colors';
            }

            // Prepare Processes for each testcase
            $process = (new ProcessBuilder())
                ->setEnv('BROWSER_NAME', $browsers)
                ->setEnv('ENV', strtolower($environment))
                ->setEnv('SERVER_URL', $serverUrl)
                ->setEnv('PUBLISH_RESULTS', $publishResults)
                ->setEnv('DEBUG', $output->isDebug())
                ->setPrefix('vendor/bin/phpunit')
                ->setArguments(array_merge($phpunitArgs, [$fileName]))
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

        if ($invalidDependencies = $processSet->checkDependencies()) {
            $output->writeln(
                sprintf(
                    '<fg=black;bg=yellow>'
                    . 'Found invalid @delayAfter dependencies (in %s). These testcases were not queued.'
                    . '</fg=black;bg=yellow>',
                    implode(', ', $invalidDependencies)
                )
            );
        }

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
                    continue;
                }

                $processOutput = $processObject->process->getIncrementalOutput();
                if ($processOutput) {
                    $processOutputLines = explode("\n", $processOutput);

                    // color output lines containing "[WARN]"
                    foreach ($processOutputLines as &$processOutputLine) {
                        if (strpos($processOutputLine, '[WARN]') !== false) {
                            $processOutputLine = '<fg=black;bg=yellow>' . $processOutputLine . '</fg=black;bg=yellow>';
                        }
                    }
                    $output->write(implode("\n", $processOutputLines));
                }

                $output->write('<error>' . $processObject->process->getIncrementalErrorOutput() . '</error>');

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
}
