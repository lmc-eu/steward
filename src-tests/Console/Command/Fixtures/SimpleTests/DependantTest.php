<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command\Fixtures\SimpleTests;

use Lmc\Steward\Component\Legacy;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * @delayAfter Lmc\Steward\Console\Command\Fixtures\SimpleTests\SimpleTest
 * @delayMinutes 0
 */
class DependantTest extends AbstractTestCase
{
    public function testFooBar(): void
    {
        // Test data from legacy were properly read
        $legacy = new Legacy($this);
        $this->assertSame(['fooBarData'], $legacy->loadWithName('dummy-data'));
    }
}
