<?php

namespace Lmc\Steward\WebDriver;

/**
 * Extends RemoteWebDriver.
 * @copyright LMC s.r.o.
 */
class RemoteWebDriver extends \RemoteWebDriver
{
    public function get($url)
    {
        $this->log('Loading URL "%s"', $url);

        return parent::get($url);
    }

    public function execute($command_name, $params = [])
    {
        if (DEBUG) {
            $this->log(
                'Executing command "%s" with params %s',
                $command_name,
                json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }

        return parent::execute($command_name, $params);
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
