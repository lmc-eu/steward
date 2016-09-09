<?php

namespace Lmc\Steward\Console\Command\Fixtures\NoBrowserTests;

use Lmc\Steward\Test\AbstractTestCase;

/**
 * Tests that don't need a browser
 * @noBrowser
 */
class NoBrowserTest extends AbstractTestCase
{
    public function testDummy()
    {
        $this->assertTrue(true);
    }
}
