<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command\Fixtures\ParallelTests;

use Lmc\Steward\Test\AbstractTestCase;

/**
 * @delayAfter Lmc\Steward\Console\Command\Fixtures\ParallelTests\FirstTest
 * @delayMinutes 0
 */
class TestDependingOnFirstTest extends AbstractTestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testMethod1(): void
    {
    }
}
