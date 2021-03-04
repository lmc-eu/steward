<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command;

use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Configuration\ConfigOptions;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Console\Event\ExtendedConsoleEvent;
use Lmc\Steward\Exception\CommandException;
use Lmc\Steward\Process\ExecutionLoop;
use Lmc\Steward\Process\MaxTotalDelayStrategy;
use Lmc\Steward\Process\ProcessSetCreator;
use Lmc\Steward\Publisher\XmlPublisher;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use OndraM\CiDetector\CiDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
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
    /** @var array Lowercase name => WebDriver identifier */
    protected $supportedBrowsers = [
        'firefox' => WebDriverBrowserType::FIREFOX,
        'chrome' => WebDriverBrowserType::CHROME,
        'microsoftedge' => WebDriverBrowserType::MICROSOFT_EDGE,
        'internet explorer' => WebDriverBrowserType::IE,
        'safari' => WebDriverBrowserType::SAFARI,
    ];

    public const ARGUMENT_ENVIRONMENT = 'environment';
    public const ARGUMENT_BROWSER = 'browser';
    public const OPTION_SERVER_URL = 'server-url';
    public const OPTION_CAPABILITY = 'capability';
    public const OPTION_TESTS_DIR = 'tests-dir';
    public const OPTION_LOGS_DIR = 'logs-dir';
    public const OPTION_PATTERN = 'pattern';
    public const OPTION_GROUP = 'group';
    public const OPTION_EXCLUDE_GROUP = 'exclude-group';
    public const OPTION_FILTER = 'filter';
    public const OPTION_NO_EXIT = 'no-exit';
    public const OPTION_IGNORE_DELAYS = 'ignore-delays';
    public const OPTION_PARALLEL_LIMIT = 'parallel-limit';

    /**
     * @internal
     */
    public function setSeleniumAdapter(SeleniumServerAdapter $seleniumAdapter): void
    {
        $this->seleniumAdapter = $seleniumAdapter;
    }

    /**
     * @internal
     */
    public function setProcessSetCreator(ProcessSetCreator $processSetCreator): void
    {
        $this->processSetCreator = $processSetCreator;
    }

    /**
     * Configure command
     */
    protected function configure(): void
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
                'Selenium server (hub) URL ',
                'http://localhost:4444/wd/hub'
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
                STEWARD_BASE_DIR . DIRECTORY_SEPARATOR . 'tests'
            )
            ->addOption(
                self::OPTION_LOGS_DIR,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to directory with logs',
                STEWARD_BASE_DIR . DIRECTORY_SEPARATOR . 'logs'
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
            )
            ->addOption(
                self::OPTION_PARALLEL_LIMIT,
                'l',
                InputOption::VALUE_REQUIRED,
                'Number of maximum testcases being executed in a parallel',
                50
            );

        $this->addUsage('staging firefox');
        $this->addUsage(
            '--group=foo --group=bar --exclude-group=baz --server-url=http://localhost:4444/wd/hub -vv staging chrome'
        );

        $this->getDispatcher()->dispatch(new BasicConsoleEvent($this), CommandEvents::CONFIGURE);
    }

    /**
     * Initialize, check arguments and options values etc.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $output->writeln(
            sprintf(
                '<info>Steward</info> <comment>%s</comment> is running the tests...%s',
                $this->getApplication()->getVersion(),
                (!(new CiDetector())->isCiDetected() ? ' Just for you <fg=red><3</fg=red>!' : '')
            )
        );

        // If browser name or env is empty, ends initialization and let the Console/Command fail on input validation
        if (empty($input->getArgument(self::ARGUMENT_BROWSER))
            || empty($input->getArgument(self::ARGUMENT_ENVIRONMENT))
        ) {
            return;
        }

        // Browser name is case insensitive, normalize it to lower case
        $browserNormalized = mb_strtolower($input->getArgument(self::ARGUMENT_BROWSER));

        // Check if browser is supported
        if (!isset($this->supportedBrowsers[$browserNormalized])) {
            throw CommandException::forUnsupportedBrowser($browserNormalized, array_keys($this->supportedBrowsers));
        }

        // Set WebDriver browser identifier back to the argument value
        $input->setArgument(self::ARGUMENT_BROWSER, $this->supportedBrowsers[$browserNormalized]);

        if ($output->isVerbose()) {
            $output->writeln(sprintf('Browser: %s', $input->getArgument(self::ARGUMENT_BROWSER)));
            $output->writeln(sprintf('Environment: %s', $input->getArgument(self::ARGUMENT_ENVIRONMENT)));
        }

        // Initialize Selenium server adapter and normalize server URL
        $seleniumAdapter = $this->getSeleniumAdapter($input->getOption(self::OPTION_SERVER_URL));
        $input->setOption(self::OPTION_SERVER_URL, $seleniumAdapter->getServerUrl());

        // Make sure parallel-limit is greater than 0
        $parallelLimit = (int) $input->getOption(self::OPTION_PARALLEL_LIMIT);
        if ($parallelLimit === 0) {
            throw new CommandException('Parallel limit must be a whole number greater than 0');
        }
        $input->setOption(self::OPTION_PARALLEL_LIMIT, $parallelLimit);

        $this->getDispatcher()->dispatch(
            new ExtendedConsoleEvent($this, $input, $output),
            CommandEvents::RUN_TESTS_INIT
        );

        if ($output->isVeryVerbose()) {
            $output->writeln(
                sprintf('Path to logs: %s', $this->config[ConfigOptions::LOGS_DIR])
            );
            $output->writeln(
                sprintf('Ignore delays: %s', ($input->getOption(self::OPTION_IGNORE_DELAYS)) ? 'yes' : 'no')
            );
            $output->writeln(
                sprintf('Parallel limit: %d', $input->getOption(self::OPTION_PARALLEL_LIMIT))
            );
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->testSeleniumConnection()) {
            return 1;
        }

        // Find all files holding test-cases
        if ($this->io->isVeryVerbose()) {
            $this->io->writeln('Searching for testcases:');
            $this->io->writeln(sprintf(' - in directory "%s"', $this->config[ConfigOptions::TESTS_DIR]));
            $this->io->writeln(sprintf(' - by pattern "%s"', $input->getOption(self::OPTION_PATTERN)));
        }

        $files = (new Finder())
            ->files()
            ->in($this->config[ConfigOptions::TESTS_DIR])
            ->name($input->getOption(self::OPTION_PATTERN));

        if (!count($files)) {
            $this->io->error(
                sprintf(
                    'No testcases found, exiting.%s',
                    !$this->io->isVeryVerbose() ? ' (use -vv or -vvv option for more information)' : ''
                )
            );

            return 1;
        }

        $processSetCreator = $this->getProcessSetCreator($input, $this->io);
        $processSet = $processSetCreator->createFromFiles(
            $files,
            $input->getOption(self::OPTION_GROUP),
            $input->getOption(self::OPTION_EXCLUDE_GROUP),
            $input->getOption(self::OPTION_FILTER),
            $input->getOption(self::OPTION_IGNORE_DELAYS)
        );

        if (!count($processSet)) {
            $this->io->error('No testcases matched given groups, exiting.');

            return 1;
        }

        $maxParallelLimit = (int) $input->getOption(self::OPTION_PARALLEL_LIMIT);

        $executionLoop = new ExecutionLoop($processSet, $this->io, new MaxTotalDelayStrategy(), $maxParallelLimit);

        $allTestsPassed = $executionLoop->start();

        if ($input->getOption(self::OPTION_NO_EXIT)) {
            return 0;
        }

        return $allTestsPassed ? 0 : 1;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getSeleniumAdapter(string $seleniumServerUrl): SeleniumServerAdapter
    {
        if ($this->seleniumAdapter === null) {
            $this->seleniumAdapter = new SeleniumServerAdapter($seleniumServerUrl);
        }

        return $this->seleniumAdapter;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getProcessSetCreator(InputInterface $input, OutputInterface $output): ProcessSetCreator
    {
        if ($this->processSetCreator === null) {
            $xmlPublisher = new XmlPublisher();
            $xmlPublisher->setFileDir($this->config[ConfigOptions::LOGS_DIR]);
            $xmlPublisher->clean();

            $this->processSetCreator = new ProcessSetCreator($this, $input, $output, $xmlPublisher, $this->config);
        }

        return $this->processSetCreator;
    }

    /**
     * Try connection to Selenium server
     */
    protected function testSeleniumConnection(): bool
    {
        $seleniumAdapter = $this->seleniumAdapter;
        $this->io->write(
            sprintf('Selenium server (hub) url: %s, trying connection...', $seleniumAdapter->getServerUrl()),
            false,
            OutputInterface::VERBOSITY_VERY_VERBOSE
        );

        if (!$seleniumAdapter->isAccessible()) {
            $this->io->writeln(
                sprintf(
                    '<error>%s ("%s")</error>',
                    $this->io->isVeryVerbose() ? 'connection error' : 'Error connecting to Selenium server',
                    $seleniumAdapter->getLastError()
                )
            );

            $this->io->error(
                sprintf(
                    'Make sure your Selenium server is really accessible on url "%s" '
                    . 'or change it using --server-url option',
                    $seleniumAdapter->getServerUrl()
                )
            );

            return false;
        }

        if (!$seleniumAdapter->isSeleniumServer()) {
            $this->io->writeln(
                sprintf(
                    '<error>%s (%s)</error>',
                    $this->io->isVeryVerbose() ? 'unexpected response' : 'Unexpected response from Selenium server',
                    $seleniumAdapter->getLastError()
                )
            );
            $this->io->error(
                sprintf(
                    'URL "%s" is occupied by something else than Selenium server. Make sure Selenium server is really'
                    . ' accessible on this URL or change it using --server-url option.'
                    . ' If using Selenium 3.x, also make sure you are not be missing "/wd/hub" part in the server URL.',
                    $seleniumAdapter->getServerUrl()
                )
            );

            return false;
        }

        if ($this->io->isVeryVerbose()) {
            $cloudService = $seleniumAdapter->getCloudService();
            $this->io->writeln(
                'OK' . ($cloudService ? ' (' . $cloudService . ' cloud service detected)' : ''),
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );
        }

        return true;
    }
}
