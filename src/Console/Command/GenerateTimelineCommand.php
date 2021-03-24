<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
use Lmc\Steward\Exception\CommandException;
use Lmc\Steward\Publisher\XmlPublisher;
use Lmc\Steward\Timeline\TimelineDataBuilder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generate visual timeline of the latest test run (according to the results.xml file) to a standalone HTML file.
 */
class GenerateTimelineCommand extends Command
{
    public const OPTION_RESULTS_FILE = 'results-file';
    public const OPTION_OUTPUT_FILE = 'output-file';
    public const DEFAULT_OUTPUT_FILENAME = 'timeline.html';

    /** @var Filesystem */
    private $filesystem;

    public function __construct(EventDispatcher $dispatcher, string $name = null)
    {
        $this->filesystem = new Filesystem();

        parent::__construct($dispatcher, $name);
    }

    /**
     * @internal
     */
    public function setFilesystem(Filesystem $filesystem): void
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('generate-timeline')
            ->setDescription('Generates HTML file with timeline visualisation of test run')
            ->addOption(
                self::OPTION_RESULTS_FILE,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to test results xml file',
                STEWARD_BASE_DIR . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . XmlPublisher::FILE_NAME
            )
            ->addOption(
                self::OPTION_OUTPUT_FILE,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to output html file',
                STEWARD_BASE_DIR . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . self::DEFAULT_OUTPUT_FILENAME
            );

        $this->getDispatcher()->dispatch(new BasicConsoleEvent($this), CommandEvents::CONFIGURE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputResultsFilePath = $this->getInputResultsFilePathFromOption($input);
        $outputFilePath = $input->getOption(self::OPTION_OUTPUT_FILE);

        $xml = simplexml_load_file($inputResultsFilePath);
        $timelineDataBuilder = new TimelineDataBuilder($xml);

        $outputHtml = $this->assembleOutputHtml(
            $timelineDataBuilder->buildTimelineGroups(),
            $timelineDataBuilder->buildTimelineItems()
        );

        $this->filesystem->dumpFile($outputFilePath, $outputHtml);

        $this->io->success(
            sprintf('Timeline generated to file "%s"', $outputFilePath)
        );

        return 0;
    }

    private function assembleOutputHtml(array $timelineGroups, array $timelineItems): string
    {
        $htmlTemplate = $this->getHtmlTemplate();

        $output = strtr(
            $htmlTemplate,
            [
                '{{stewardVersion}}' => $this->getApplication()->getVersion(),
                '{{dateGenerated}}' => (new \DateTime())->format('Y-m-d H:i:s'),
                '{{timelineGroups}}' => json_encode($timelineGroups, JSON_THROW_ON_ERROR),
                '{{timelineItems}}' => json_encode($timelineItems, JSON_THROW_ON_ERROR),
            ]
        );

        return $output;
    }

    private function getHtmlTemplate(): string
    {
        return file_get_contents(__DIR__ . '/../../Resources/timeline-template.html');
    }

    protected function getInputResultsFilePathFromOption(InputInterface $input): string
    {
        $inputResultsFilePath = $input->getOption(self::OPTION_RESULTS_FILE);
        if (!is_readable($inputResultsFilePath)) {
            throw CommandException::forNotAccessibleResultsFile($inputResultsFilePath, self::OPTION_RESULTS_FILE);
        }

        return $inputResultsFilePath;
    }
}
