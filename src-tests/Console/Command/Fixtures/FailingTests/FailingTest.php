<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command\Fixtures\FailingTests;

use Lmc\Steward\Test\AbstractTestCase;

class FailingTest extends AbstractTestCase
{
    public function testThatWillFail(): void
    {
        $this->fail('Sorry :-(');
    }
}
