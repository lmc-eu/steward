<?php declare(strict_types=1);

namespace Lmc\Steward\Listener\Fixtures;

use Lmc\Steward\Publisher\AbstractPublisher;
use PHPUnit\Framework\Test;

class DummyPublisher extends AbstractPublisher
{
    public function publishResults(
        string $testCaseName,
        string $status,
        string $result = null,
        \DateTimeInterface $testCaseStartDate = null,
        \DateTimeInterface $testCaseEndDate = null
    ): void {
    }

    public function publishResult(
        string $testCaseName,
        string $testName,
        Test $testInstance,
        string $status,
        string $result = null,
        string $message = null
    ): void {
    }
}
