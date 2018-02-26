<?php

namespace Lmc\Steward\Listener;

use Lmc\Steward\Console\Style\StewardStyle;
use Lmc\Steward\Test\AbstractTestCase;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;

class TestStartLogListener implements TestListener
{
    use TestListenerDefaultImplementation;

    public function startTest(Test $test)
    {
        if ($test instanceof AbstractTestCase) {
            echo StewardStyle::getTimestampPrefix() . ': '
                . 'Starting execution of test ' . get_class($test) . '::' . $test->getName()
                . "\n";
        }
    }
}
