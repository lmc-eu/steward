<?php

namespace Lmc\Steward\Test;

/**
 * Abstract test case to be used by all test cases.
 * It adds loging, some common logics and assertions.
 *
 * @copyright LMC s.r.o.
 */
abstract class AbstractTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \RemoteWebDriver
     */
    public $wd;

    /**
     * Commmon test utils, instantiated on setUp
     * @var TestUtils
     */
    public $utils;

    public function setUp()
    {
        $this->log('Starting execution of test ' . get_called_class() . '::' . $this->getName());

        $this->utils = new TestUtils($this);
    }

    public function tearDown()
    {
        unset($this->utils);

        $this->log('Finished execution of test ' . get_called_class() . '::' . $this->getName());
    }

    /**
     * @todo
     */
    public function log($msg)
    {
        echo '[' . date("Y-m-d H:i:s") . ']: ' . $msg . "\n";
    }
}
