<?php

namespace Lmc\Steward\Test;

/**
 * Abstract test case base.
 * It holds RemoteWebDriver and defines function templates.
 */
abstract class AbstractTestCaseBase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \RemoteWebDriver
     */
    public $wd;

    /**
     * Log to output
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed $args,... OPTIONAL Variable number of parameters inserted into $format string
     */
    abstract public function log($format, $args = null);

    /**
     * Log warning to output. Unlike log(), it will be prefixed with "WARN: " and colored.
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed $args,... OPTIONAL Variable number of parameters inserted into $format string
     */
    abstract public function warn($format, $args = null);

    /**
     * Log to output, but only if debug mode is enabled.
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed $args,... OPTIONAL Variable number of parameters inserted into $format string
     */
    abstract public function debug($format, $args = null);
}
