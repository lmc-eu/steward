<?php declare(strict_types=1);

namespace Lmc\Steward\Listener\Fixtures;

use Lmc\Steward\Publisher\AbstractPublisher;
use PHPUnit\Framework\Test;

/**
 * Throw an exception anytime its public methods are called
 */
class ExceptionThrowingPublisher extends AbstractPublisher
{
    public function publishResults(
        string $testCaseName,
        string $status,
        string $result = null,
        \DateTimeInterface $testCaseStartDate = null,
        \DateTimeInterface $testCaseEndDate = null
    ): void {
        throw new \LogicException('publishResults() called');
    }

    public function publishResult(
        string $testCaseName,
        string $testName,
        Test $testInstance,
        string $status,
        string $result = null,
        string $message = null
    ): void {
        throw new \LogicException('publishResult() called');
    }
}
