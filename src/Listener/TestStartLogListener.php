<?php declare(strict_types=1);

namespace Lmc\Steward\Listener;

use Lmc\Steward\Console\Style\StewardStyle;
use Lmc\Steward\Test\AbstractTestCase;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;

class TestStartLogListener implements TestListener
{
    use TestListenerDefaultImplementation;

    public function startTest(Test $test): void
    {
        if ($test instanceof AbstractTestCase) {
            echo sprintf(
                '%s --- Starting execution of test "%s" ---',
                StewardStyle::getTimestampPrefix(),
                $test->getName()
            );
            echo "\n";
        }
    }
}
