<?php

namespace Lmc\Steward\Test;

/**
 * Extends RemoteWebDriver.
 * @copyright LMC s.r.o.
 */
class RemoteWebDriver extends \RemoteWebDriver
{
    public function get($url)
    {
        $this->log('Loading URL "%s"', $url);
        parent::get($url);
    }

    /**
     * Log to output
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed $args,... OPTIONAL Variable number of parameters inserted into $format string
     */
    protected function log($format, $args = null)
    {
        $argv = func_get_args();
        $format = array_shift($argv);
        echo '[' . date("Y-m-d H:i:s") . ']:'
            . ' [WebDriver] '
            . vsprintf($format, $argv)
            . "\n";
    }
}
