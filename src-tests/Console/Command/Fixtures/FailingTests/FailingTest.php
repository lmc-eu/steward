<?php

namespace Lmc\Steward\Console\Command\Fixtures\FailingTests;

use Lmc\Steward\Test\AbstractTestCase;

class FailingTest extends AbstractTestCase
{
    public function testThatWillFail()
    {
        $this->fail('Sorry :-(');
    }
}
