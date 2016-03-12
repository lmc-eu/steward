<?php

namespace Lmc\Steward\Process\Fixtures\InvalidTests;

use Lmc\Steward\Test\AbstractTestCase;

/**
 * Test with defined delay length (@delayMinutes) but missing name of the class (@delayAfter)
 *
 * @delayMinutes 5
 */
class InvalidDelayTest extends AbstractTestCase
{
}
