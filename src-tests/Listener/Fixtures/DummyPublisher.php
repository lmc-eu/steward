<?php

namespace Lmc\Steward\Listener\Fixtures;

use Lmc\Steward\Publisher\AbstractPublisher;
use PHPUnit\Framework\Test;

class DummyPublisher extends AbstractPublisher
{
    public function publishResults(
        $testCaseName,
        $status,
        $result = null,
        \DateTimeInterface $startDate = null,
        \DateTimeInterface $endDate = null
    ): void {
    }

    public function publishResult(
        $testCaseName,
        $testName,
        Test $testInstance,
        $status,
        $result = null,
        $message = null
    ): void {
    }
}
