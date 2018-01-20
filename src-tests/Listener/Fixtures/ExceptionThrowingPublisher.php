<?php

namespace Lmc\Steward\Listener\Fixtures;

use Lmc\Steward\Publisher\AbstractPublisher;
use PHPUnit\Framework\Test;

/**
 * Throw an exception anytime its public methods are called
 */
class ExceptionThrowingPublisher extends AbstractPublisher
{
    public function publishResults(
        $testCaseName,
        $status,
        $result = null,
        \DateTimeInterface $startDate = null,
        \DateTimeInterface $endDate = null
    ) {
        throw new \LogicException('publishResults() called');
    }

    public function publishResult(
        $testCaseName,
        $testName,
        Test $testInstance,
        $status,
        $result = null,
        $message = null
    ) {
        throw new \LogicException('publishResult() called');
    }
}
