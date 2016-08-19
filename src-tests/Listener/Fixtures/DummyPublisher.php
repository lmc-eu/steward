<?php

namespace Lmc\Steward\Listener\Fixtures;

use Lmc\Steward\Publisher\AbstractPublisher;

class DummyPublisher extends AbstractPublisher
{
    public function publishResults(
        $testCaseName,
        $status,
        $result = null,
        \DateTimeInterface $startDate = null,
        \DateTimeInterface $endDate = null
    ) {
    }

    public function publishResult(
        $testCaseName,
        $testName,
        \PHPUnit_Framework_Test $testInstance,
        $status,
        $result = null,
        $message = null
    ) {
    }
}
