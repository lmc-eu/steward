<?php

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

    /**
     * @return array
     */
    public function buildTimelineGroups()
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

    /**
     * @return array
     */
    public function buildTimelineItems()
    {
        $testElements = $this->xml->xpath('//testcase/test[@status="done"]');
        $timelineItems = [];

        foreach ($testElements as $testElement) {
            $timelineItems[] = [
                'group' => $this->resolveTestExecutorId($testElement),
                'content' => (string) $testElement['name'],
                'title' => $this->assembleFullTestName($testElement),
                'start' => (string) $testElement['start'],
                'end' => (string) $testElement['end'],
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
    private function getExecutors()
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

    /**
     * @param \SimpleXMLElement $testElement
     * @return string
     */
    private function assembleFullTestName(\SimpleXMLElement $testElement)
    {
        $parentElement = $testElement->xpath('..');
        $testcaseName = (string) reset($parentElement)['name'];

        return $testcaseName . '::' . $testElement['name'];
    }

    /**
     * @param \SimpleXMLElement $testElement
     * @return string
     */
    private function resolveTestExecutorId(\SimpleXMLElement $testElement)
    {
        $testExecutor = (string) $testElement['executor'];

        if (!empty($testExecutor) && isset($this->executors[$testExecutor])) {
            return $this->executors[$testExecutor];
        }

        return 'unknown';
    }
}
