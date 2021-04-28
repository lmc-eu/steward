<?php declare(strict_types=1);

namespace Lmc\Steward\WebDriver;

use Facebook\WebDriver\Remote\DriverCommand;
use Lmc\Steward\ConfigProvider;

/**
 * Extends RemoteWebDriver with some extra logic, eg. more verbose logging.
 */
class RemoteWebDriver extends \Facebook\WebDriver\Remote\RemoteWebDriver
{
    public function get($url): \Facebook\WebDriver\Remote\RemoteWebDriver
    {
        $this->log('Loading URL "%s"', $url);

        return parent::get($url);
    }

    public function execute($command_name, $params = [])
    {
        if (ConfigProvider::getInstance()->debug) {
            if ($command_name === DriverCommand::UPLOAD_FILE) {
                $this->log('Executing command "%s"', $command_name);
            } else {
                $this->log(
                    'Executing command "%s" with params %s',
                    $command_name,
                    json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
            }
        }

        return parent::execute($command_name, $params);
    }

    /**
     * Log to output
     *
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed ...$args Variable number of parameters inserted into $format string
     */
    protected function log(string $format, ...$args): void
    {
        echo '[' . date('Y-m-d H:i:s') . ']'
            . ' [WebDriver] '
            . vsprintf($format, $args)
            . "\n";
    }
}
