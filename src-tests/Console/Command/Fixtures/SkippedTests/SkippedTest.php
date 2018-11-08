<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command\Fixtures\SkippedTests;

use Lmc\Steward\Test\AbstractTestCase;

class SkippedTest extends AbstractTestCase
{
    public function testWhichFails(): void
    {
        $this->fail();
    }

    /**
     * @depends testWhichFails
     */
    public function testsWhichShouldBeSkipped(): void
    {
    }
}
