<?php

namespace Lmc\Steward\Process\Fixtures\DelayedTests;

use Lmc\Steward\Test\AbstractTestCase;

/**
 * Tests delayed after FirstTest for 0 minutes (thus making it being run afret FirstTest but without real delay)
 *
 * @delayAfter Lmc\Steward\Process\Fixtures\DelayedTests\FirstTest
 * @delayMinutes 0
 */
class DelayedByZeroTimeTest extends AbstractTestCase
{
}
