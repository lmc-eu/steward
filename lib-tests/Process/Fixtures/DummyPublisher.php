<?php

namespace Lmc\Steward\Process\Fixtures;

use Lmc\Steward\Publisher\AbstractPublisher;

class DummyPublisher extends AbstractPublisher
{
    public function __construct($environment, $jobName, $buildNumber)
    {
    }

    public function publishResults(
        $testCaseName,
        $status,
        $result = null,
        \DateTimeInterface $startDate = null,
        \DateTimeInterface $endDate = null
    ) {
    }

    /**
     * Publish results of one single test
     *
     * @param string $testCaseName
     * @param string $testName
     * @param string $status One of self::$testStatuses
     * @param string $result One of self::$testResults
     * @param string $message
     */
    public function publishResult($testCaseName, $testName, $status, $result = null, $message = null)
    {
    }
}
