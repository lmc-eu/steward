<?php

namespace Lmc\Steward\Process\Fixtures\DelayedTests;

use Lmc\Steward\Test\AbstractTestCase;

/**
 * Tests delayed after FirstTest for 3.33 minutes
 *
 * @delayAfter Lmc\Steward\Process\Fixtures\DelayedTests\FirstTest
 * @delayMinutes 3.33
 */
class DelayedTest extends AbstractTestCase
{
}
