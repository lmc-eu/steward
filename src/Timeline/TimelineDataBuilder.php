<?php declare(strict_types=1);

namespace Lmc\Steward\Timeline;

/**
 * Process results xml data to build data-sets for timeline visualisation via Vis.js
 */
class TimelineDataBuilder
{
    /** @var \SimpleXMLElement */
    private $xml;
    /** @var array Array of executors, key = executor URL, value = executor ID */
    private $executors;

    /**
     * @param \SimpleXMLElement $xml XML read from results.xml file
     */
    public function __construct(\SimpleXMLElement $xml)
    {
        $this->xml = $xml;
        $this->executors = $this->getExecutors();
    }

    public function buildTimelineGroups(): array
    {
        $timelineGroups = [];

        foreach ($this->executors as $executorUrl => $executorId) {
            $timelineGroups[] = [
                'id' => $executorId,
                'content' => $executorUrl,
                'title' => $executorUrl,
            ];
        }

        return $timelineGroups;
    }

    public function buildTimelineItems(): array
    {
        $testElements = $this->xml->xpath('//testcase/test[@status="done"]');
        $timelineItems = [];

        foreach ($testElements as $testElement) {
            $timelineItems[] = [
                'group' => $this->resolveTestExecutorId($testElement),
                'content' => (string) $testElement['name'],
                'title' => $this->buildItemTitle($testElement),
                'start' => $this->toCompatibleDateFormat((string) $testElement['start']),
                'end' => $this->toCompatibleDateFormat((string) $testElement['end']),
                'className' => (string) $testElement['result'],
            ];
        }

        return $timelineItems;
    }

    /**
     * Get array of unique executors
     *
     * @return array Array of executors, key = executor URL, value = executor ID
     */
    private function getExecutors(): array
    {
        $testElements = $this->xml->xpath('//testcase/test[@status="done"]');
        $hasTestWithoutExecutor = false;
        $executors = [];

        foreach ($testElements as $testElement) {
            $executorValue = (string) $testElement['executor'];

            if (!empty($executorValue)) {
                $executors[] = $executorValue;
            } else {
                $hasTestWithoutExecutor = true;
            }
        }

        $executors = array_unique($executors);
        // sort and reindex
        sort($executors);
        // use executors index as a value and executor name as a key
        $executors = array_flip($executors);

        if ($hasTestWithoutExecutor) {
            $executors['unknown'] = 'unknown';
        }

        return $executors;
    }

    private function buildItemTitle(\SimpleXMLElement $testElement): string
    {
        return sprintf(
            '%s<br>(%d sec)',
            $this->assembleFullTestName($testElement),
            $this->calculateTestDurationSeconds((string) $testElement['start'], (string) $testElement['end'])
        );
    }

    private function assembleFullTestName(\SimpleXMLElement $testElement): string
    {
        $parentElement = $testElement->xpath('..');
        $testcaseName = (string) reset($parentElement)['name'];

        return $testcaseName . '::' . $testElement['name'];
    }

    private function calculateTestDurationSeconds(string $start, string $end): int
    {
        return (new \DateTimeImmutable($end))->getTimestamp() - (new \DateTimeImmutable($start))->getTimestamp();
    }

    private function resolveTestExecutorId(\SimpleXMLElement $testElement): string
    {
        $testExecutor = (string) $testElement['executor'];

        if (!empty($testExecutor) && isset($this->executors[$testExecutor])) {
            return (string) $this->executors[$testExecutor];
        }

        return 'unknown';
    }

    /**
     * Convert ISO date to compatible date format (drop timezone).
     *
     * @see https://stackoverflow.com/q/6427204/464890
     */
    private function toCompatibleDateFormat(string $isoDate): string
    {
        $date = new \DateTimeImmutable($isoDate);

        return $date->format('Y-m-d\TH:i:s');
    }
}
