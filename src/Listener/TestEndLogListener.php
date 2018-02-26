<?php

namespace Lmc\Steward\Listener;

use Lmc\Steward\Test\AbstractTestCase;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;

class TestEndLogListener implements TestListener
{
    use TestListenerDefaultImplementation;

    public function endTest(Test $test, $time)
    {
        if ($test instanceof AbstractTestCase) {
            $test->appendTestLog('Finished execution of test ' . get_class($test) . '::' . $test->getName());
        }
    }
}
