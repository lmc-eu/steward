<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command\Fixtures\NoBrowserTests;

use Lmc\Steward\Test\AbstractTestCase;

/**
 * Tests that don't need a browser
 *
 * @noBrowser
 */
class NoBrowserTest extends AbstractTestCase
{
    public function testDummy(): void
    {
        $this->assertTrue(true);
    }
}
