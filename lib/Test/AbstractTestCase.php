<?php

namespace Lmc\Steward\Test;

use Lmc\Steward\Component\TestUtils;

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

    /**
     * Names of existing LMC environments
     * @todo TODO: remove - is LMC specific
     * @var array
     */
    public static $lmcEnvs = ['dev1', 'dev2', 'dev3', 'devel', 'deploy', 'prod'];

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
        echo $this->formatOutput(func_get_args());
    }

    /**
     * Log warning to output. Unlike log(), it will be prefixed with "WARN: " and colored.
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed $args,... OPTIONAL Variable number of parameters inserted into $format string
     */
    public function warn($format, $args = null)
    {
        echo $this->formatOutput(func_get_args(), $isWarn = true);
    }

    /**
     * Format output
     * @param array $args Array of arguments passed to original sprintf()-like function
     * @param bool $isWarn OPTIONAL Is message warning? Default is false.
     * @return string Formatted output
     */
    protected function formatOutput(array $args, $isWarn = false)
    {
        $format = array_shift($args);

        // If first item of arguments contains another array use it as arguments
        if (!empty($args) && is_array($args[0])) {
            $args = $args[0];
        }

        return '[' . date("Y-m-d H:i:s") . ']'
            . ($isWarn ? ' [WARN]' : '')
            . ': '
            . vsprintf($format, $args)
            . "\n";
    }
}
