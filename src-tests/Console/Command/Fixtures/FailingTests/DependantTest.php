<?php

namespace Lmc\Steward\Console\Command\Fixtures\FailingTests;

use Lmc\Steward\Component\Legacy;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * Test depending on failed tests (thus it won't be started at all and be marked as failed).
 *
 * @delayAfter Lmc\Steward\Console\Command\Fixtures\FailingTests\FailingTest
 * @delayMinutes 0
 */
class DependantTest extends AbstractTestCase
{
    public function testFooBar()
    {
        $this->assertTrue(true);
    }
}
