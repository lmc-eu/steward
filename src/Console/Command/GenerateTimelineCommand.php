<?php

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\CommandEvents;
use Lmc\Steward\Console\Event\BasicConsoleEvent;
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
    const OPTION_RESULTS_FILE = 'results-file';
    const OPTION_OUTPUT_FILE = 'output-file';
    const DEFAULT_OUTPUT_FILENAME = 'timeline.html';

    /** @var Filesystem */
    private $filesystem;

    public function __construct(EventDispatcher $dispatcher, $name = null)
    {
        $this->filesystem = new Filesystem();

        parent::__construct($dispatcher, $name);
    }

    /**
     * @internal
     * @param Filesystem $filesystem
     */
    public function setFilesystem(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('generate-timeline')
            ->setDescription('Generates HTML file with timeline visualisation of test run')
            ->addOption(
                self::OPTION_RESULTS_FILE,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to test results xml file',
                realpath(STEWARD_BASE_DIR . '/logs/' . XmlPublisher::FILE_NAME)
            )
            ->addOption(
                self::OPTION_OUTPUT_FILE,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to output html file',
                realpath(STEWARD_BASE_DIR . '/logs/') . '/' . self::DEFAULT_OUTPUT_FILENAME
            );

        $this->getDispatcher()->dispatch(CommandEvents::CONFIGURE, new BasicConsoleEvent($this));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
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

    /**
     * @param array $timelineGroups
     * @param array $timelineItems
     * @return string
     */
    private function assembleOutputHtml(array $timelineGroups, array $timelineItems)
    {
        $htmlTemplate = $this->getHtmlTemplate();

        $output = strtr(
            $htmlTemplate,
            [
                '{{stewardVersion}}' => $this->getApplication()->getVersion(),
                '{{dateGenerated}}' => (new \DateTime())->format('Y-m-d H:i:s'),
                '{{timelineGroups}}' => json_encode($timelineGroups),
                '{{timelineItems}}' => json_encode($timelineItems),
            ]
        );

        return $output;
    }

    /**
     * @return string
     */
    private function getHtmlTemplate()
    {
        $html = file_get_contents(__DIR__ . '/../../Resources/timeline-template.html');

        return $html;
    }

    /**
     * @param InputInterface $input
     * @return mixed
     */
    protected function getInputResultsFilePathFromOption(InputInterface $input)
    {
        $inputResultsFilePath = $input->getOption(self::OPTION_RESULTS_FILE);
        if (!is_readable($inputResultsFilePath)) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot read results file "%s", make sure it is accessible '
                    . '(or use --%s option if it is stored elsewhere)',
                    $inputResultsFilePath,
                    self::OPTION_RESULTS_FILE
                )
            );
        }

        return $inputResultsFilePath;
    }
}
