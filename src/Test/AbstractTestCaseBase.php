<?php

namespace Lmc\Steward\Test;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use PHPUnit\Framework\TestCase;

/**
 * Abstract test case base.
 * It holds RemoteWebDriver and defines function templates.
 */
abstract class AbstractTestCaseBase extends TestCase
{
    /** @var RemoteWebDriver */
    public $wd;

    /**
     * Log to output
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed ...$args Variable number of parameters inserted into $format string
     */
    abstract public function log($format, ...$args);

    /**
     * Log warning to output. Unlike log(), it will be prefixed with "WARN: " and colored.
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed ...$args Variable number of parameters inserted into $format string
     */
    abstract public function warn($format, ...$args);

    /**
     * Log to output, but only if debug mode is enabled.
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed ...$args Variable number of parameters inserted into $format string
     */
    abstract public function debug($format, ...$args);
}
