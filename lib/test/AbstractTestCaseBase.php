<?php

namespace Lmc\Steward\Test;

/**
 * Abstract test case base
 * It holds RemoteWebDriver
 * and defines function templates
 *
 * @copyright LMC s.r.o.
 */
abstract class AbstractTestCaseBase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \RemoteWebDriver
     */
    public $wd;

    abstract public function log($msg);
}
