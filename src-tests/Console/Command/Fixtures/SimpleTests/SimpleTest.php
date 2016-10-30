<?php

namespace Lmc\Steward\Console\Command\Fixtures\SimpleTests;

use Lmc\Steward\Component\Legacy;
use Lmc\Steward\Test\AbstractTestCase;

class SimpleTest extends AbstractTestCase
{
    public function testWebpage()
    {
        // Test interaction with the WebDriver and browser
        $this->wd->get('file:///' . __DIR__ . '/webpage.html');
        $this->assertSame('Page header', $this->findByCss('h1')->getText());

        // Save some dummy data to the Legacy
        $legacy = new Legacy($this);
        $legacy->saveWithName(['fooBarData'], 'dummy-data');
    }
}
