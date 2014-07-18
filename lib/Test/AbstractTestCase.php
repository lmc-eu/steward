<?php

namespace Lmc\Steward\Test;

/**
 * Abstract test case to be used by all test cases.
 * It adds logging, some common logic and assertions.
 *
 * @copyright LMC s.r.o.
 */
abstract class AbstractTestCase extends AbstractTestCaseBase
{
    /**
     * Common test utils, instantiated on setUp.
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
        $this->log('Finished execution of test ' . get_called_class() . '::' . $this->getName());
    }

    /**
     * Log to output
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed $args,... OPTIONAL Variable number of parameters inserted into $format string
     */
    public function log($format, $args = null)
    {
        $argv = func_get_args();
        $format = array_shift($argv);

        echo '[' . date("Y-m-d H:i:s") . ']: '
            . vsprintf($format, $argv)
            . "\n";
    }
}
