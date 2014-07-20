<?php

namespace Lmc\Steward;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\Process;
use Nette\Utils\Finder;
use Nette\Reflection\AnnotationsParser;

/**
 * Run tests command is used to start Steward test planner and execute tests one by one,
 * optionaly with defined delay.
 *
 * @copyright LMC s.r.o.
 */
class RunTestsCommand extends Command
{
    /**
     * Array of objects with test processes, indexed by testcase fully qualified name
     * @var array
     */
    protected $processes = [];

    /**
     * Configure command
     */
    protected function configure()
    {
         $this->setName('run-tests')
            ->setDescription('Run tests planner and execute tests')
            ->addArgument(
                'browser',
                InputArgument::OPTIONAL, // TODO: IS_ARRAY to allow multiple browsers?
                'Browsers in which test should be run',
                'phantomjs'
            )
            ->addOption(
                'lmc-env',
                null,
                InputOption::VALUE_REQUIRED,
                'LMC environment name, use unknown for localhost',
                'unknown'
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
                realpath(__DIR__ . '/../tests')
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
                'Only runs tests from the specified @group (option is passed to PHPUnit)'
            );
    }

    /**
     * Execute command
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

        $pattern = $input->getOption('pattern');
        $dir = $input->getOption('dir');
        $lmcEnv = $input->getOption('lmc-env');
        $serverUrl = $input->getOption('server-url');
        $parsedUrl = parse_url($serverUrl);
        $group = $input->getOption('group');

        $output->writeln(sprintf('Browser: %s', $browsers));
        $output->writeln(sprintf('LMC environment: %s', $lmcEnv));
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

        $testCasesNum = 0;
        foreach (Finder::findFiles($pattern)->from($dir) as $fileName => $fileObject) {
            // Parse classes from the testcase file
            $classes = AnnotationsParser::parsePhp(\file_get_contents($fileName));

            // Get annotations for the first class in testcase (one file = one class)
            $annotations = AnnotationsParser::getAll(new \ReflectionClass(key($classes)));

            // If group is specified, but the class does not have it, skip the test now
            if ($group) {
                if (!in_array($group, $annotations['group'])) {
                    continue;
                }
                $output->writeln(
                    sprintf(
                        'Found testcase file #%d in group %s: %s',
                        ++$testCasesNum,
                        $group,
                        $fileName
                    )
                );
            } else {
                $output->writeln(sprintf('Found testcase file #%d: %s', ++$testCasesNum, $fileName));
            }

            $phpunitArgs = [
                '--log-junit=logs/' . $fileObject->getFileName() . '.xml',
                '--configuration=lib/Test/phpunit.xml',
            ];

            // Prepare Processes for each testcase
            $process = (new ProcessBuilder())
                ->setEnv('BROWSER_NAME', $browsers)
                ->setEnv('LMC_ENV', $lmcEnv)
                ->setEnv('SERVER_URL', $serverUrl)
                ->setPrefix('vendor/bin/phpunit')
                ->setArguments(array_merge($phpunitArgs, [$fileName]))
                ->getProcess();

            $this->addProcessToQueue(
                $process,
                key($classes),
                $delayAfter = !empty($annotations['delayAfter']) ? current($annotations['delayAfter']) : '',
                $delayMinutes = !empty($annotations['delayMinutes']) ? current($annotations['delayMinutes']) : 0
            );
        }

        if (!count($this->processes)) {
            $output->writeln('No testcases matched given criteria, exiting.');
            return 1;
        }

        // Ensure dependencies links to existing classes
        $queuedProcesses = $this->getProcesses('queued');
        foreach ($queuedProcesses as $className => $processObject) {
            if (!empty($processObject->delayAfter)
                && !array_key_exists($processObject->delayAfter, $queuedProcesses)) {
                $output->writeln(sprintf('Testcase "%s" has invalid dependency, not queueing it.', $className));
                $this->removeProcess($className);
            }
        }

        // Set tasks without delay as prepared in order to make them executed instantly
        $queuedProcesses = $this->getProcesses('queued');
        foreach ($queuedProcesses as $className => $processObject) {
            if (!$processObject->delayMinutes) {
                $output->writeln(sprintf('Testcase "%s" is prepared to be run', $className));
                $processObject->status = 'prepared';
            } else {
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

        $this->executionLoop($output);
    }

    /**
     * Add new process to the queue
     * @param Process $process PHPUnit process to run
     * @param string $className Tested class fully qualified name
     * @param string $delayAfter OPTIONAL Other fully qualified class name after which this test should be run.
     * If is set, $delayMinutes must be > 0
     * @param int $delayMinutes OPTIONAL Delay execution for $delayMinutes after $delayAfter test
     */
    protected function addProcessToQueue(Process $process, $className, $delayAfter = '', $delayMinutes = 0)
    {
        $delayMinutes = abs((int) $delayMinutes);
        if (!empty($delayAfter) && $delayMinutes === 0) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Test "%s" should run after "%s", but not delay was defined',
                    $className,
                    $delayAfter
                )
            );
        }
        if ($delayMinutes !== 0 && empty($delayAfter)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Test "%s" has defined delay %d minutes, but does not have defined the task to run after',
                    $className,
                    $delayMinutes
                )
            );
        }

        $this->processes[$className] = (object) [
            'status' => 'queued',
            'process' => $process,
            'delayAfter' => $delayAfter,
            'delayMinutes' => $delayMinutes,
            'finishedTime' => null,
        ];
    }

    /**
     * Get array of processes having given status
     * @param string $status
     * @return array
     */
    protected function getProcesses($status)
    {
        $return = [];
        foreach ($this->processes as $className => $processObject) {
            if ($processObject->status == $status) {
                $return[$className] = $processObject;
            }
        }

        return $return;
    }

    /**
     * Remove process - no matter its status
     * @param type $className
     */
    protected function removeProcess($className)
    {
        unset($this->processes[$className]);
    }

    /**
     * Start planner execution loop
     * @param OutputInterface $output
     */
    protected function executionLoop(OutputInterface $output)
    {
        // Iterate over prepared and queued until everything is done
        while (true) {
            $prepared = $this->getProcesses('prepared');
            $queued = $this->getProcesses('queued');

            if (count($prepared) == 0 && count($queued) == 0) {
                $output->writeln('No tasks left, exiting the execution loop...');
                break;
            }

            // Start all prepared tasks and set status of not running as finished
            foreach ($prepared as $testClass => $processObject) {
                if (!$processObject->process->isStarted()) {
                    $output->writeln(
                        sprintf(
                            'Running command for class "%s":' . "\n" . '%s',
                            $testClass,
                            $processObject->process->getCommandLine()
                        )
                    );
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
                    $processObject->status = 'finished';
                    $processObject->finishedTime = time();
                }
            }

            // Add queued tasks to prepared if their dependent task is done and delay has passed
            $finished = $this->getProcesses('finished');
            $finishedClasses = [];
            foreach ($finished as $testClass => $processObject) {
                $finishedClasses[] = $testClass;
            }
            foreach ($queued as $testClass => $processObject) {
                $delaySeconds = $processObject->delayMinutes * 60;

                if (in_array($processObject->delayAfter, $finishedClasses)
                    && (time() - $finished[$processObject->delayAfter]->finishedTime) > $delaySeconds) {
                    $output->writeln(sprintf('Unqueing class "%s"', $testClass));
                    $processObject->status = 'prepared';
                }
            }

            $output->writeln(
                sprintf(
                    "waiting (running: %d, queued: %d, finished: %d)",
                    count($this->getProcesses('prepared')),
                    count($this->getProcesses('queued')),
                    count($this->getProcesses('finished'))
                )
            );
            sleep(1);
        }
    }
}
