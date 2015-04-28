<?php

namespace Lmc\Steward\Test;

/**
 * Selenium server API adapter, used mainly to check connection (communication with Selenium server handles the
 * \WebDriverCommandExecutor, not this adapter).
 */
class SeleniumAdapter
{
    /** @var string */
    protected $lastError;

    /**
     * Get description of last error
     * @return string|null
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Test if server URL is accessible
     *
     * @param string $seleniumServerUrl
     * @return bool
     */
    public function isAccessible($seleniumServerUrl)
    {
        $urlParts = parse_url($seleniumServerUrl);

        // Check connection to server is possible
        $seleniumConnection = @fsockopen($urlParts['host'], $urlParts['port'], $connectionErrorNo, $connectionError, 5);
        if (!is_resource($seleniumConnection)) {
            $this->lastError = $connectionError;
            return false;
        }
        fclose($seleniumConnection);

        return true;
    }

    /**
     * Test if server is really an Selenium server
     *
     * @param string $seleniumServerUrl
     * @return bool
     */
    public function isSeleniumServer($seleniumServerUrl)
    {
        // Check server properly responds to http requests
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $responseData = @file_get_contents($seleniumServerUrl . '/wd/hub/status/', false, $context);

        if (!$responseData) {
            $this->lastError = 'error reading server response';
            return false;
        }

        if (!json_decode($responseData)) {
            $this->lastError = 'error parsing server JSON response (' . json_last_error_msg() . ')';
            return false;
        }

        return true;
    }
}
