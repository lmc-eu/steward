<?php

namespace Lmc\Steward\WebDriver;

use DesiredCapabilities;
use DriverCommand;
use HttpCommandExecutor;
use WebDriverCommand;

/**
 * Extends RemoteWebDriver.
 * @copyright LMC s.r.o.
 */
class RemoteWebDriver extends \RemoteWebDriver
{
    /**
     * Construct the RemoteWebDriver by a desired capabilities.
     *
     * @param string $url The url of the remote server
     * @param DesiredCapabilities $desired_capabilities The desired capabilities
     * @param int $connection_timeout_in_ms
     * @param int $request_timeout_in_ms
     * @todo Remove after the possibility to set request timeout is added to upstream.
     * @see https://github.com/facebook/php-webdriver/pull/186#issuecomment-65935153
     * @return RemoteWebDriver
     */
    public static function create(
        $url = 'http://localhost:4444/wd/hub',
        $desired_capabilities = null,
        $connection_timeout_in_ms = 300000,
        $request_timeout_in_ms = 300000
    ) {
        $url = preg_replace('#/+$#', '', $url);

        // Passing DesiredCapabilities as $desired_capabilities is encourged but
        // array is also accepted for legacy reason.
        if ($desired_capabilities instanceof DesiredCapabilities) {
            $desired_capabilities = $desired_capabilities->toArray();
        }

        $executor = new HttpCommandExecutor($url);
        $executor->setConnectionTimeout($connection_timeout_in_ms);
        $executor->setRequestTimeout($request_timeout_in_ms);

        $command = new WebDriverCommand(
            null,
            DriverCommand::NEW_SESSION,
            ['desiredCapabilities' => $desired_capabilities]
        );

        $response = $executor->execute($command);

        $driver = new static();
        $driver->setSessionID($response->getSessionID())
            ->setCommandExecutor($executor);
        return $driver;
    }

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
