<?php

namespace Lmc\Steward\Selenium;

/**
 * Selenium server API adapter, used mainly to check connection (communication with Selenium server handles the
 * WebDriverCommandExecutor, not this adapter).
 */
class SeleniumServerAdapter
{
    const HUB_ENDPOINT = '/wd/hub';
    const STATUS_ENDPOINT = '/wd/hub/status';
    const DEFAULT_PORT = 4444;
    const DEFAULT_PORT_CLOUD_SERVICE = 80;

    /** @var array */
    protected $serverUrlParts;
    /** @var string */
    protected $lastError;
    /** @var bool */
    protected $cloudService = false;

    /**
     * @param string $serverUrl
     */
    public function __construct($serverUrl)
    {
        $this->serverUrlParts = $this->parseServerUrl($serverUrl);
    }

    /**
     * Get description of last error
     * @return string|null
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * @return array
     */
    public function getServerUrlParts()
    {
        return $this->serverUrlParts;
    }

    /**
     * @return string
     */
    public function getServerUrl()
    {
        $parts = $this->serverUrlParts;

        $serverUrl = $parts['scheme'] . '://';
        $serverUrl .= isset($parts['user']) ? $parts['user'] : '';
        $serverUrl .= isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $serverUrl .= (isset($parts['user']) || isset($parts['pass'])) ? '@' : '';
        $serverUrl .= $parts['host'] . ':' . $parts['port'];
        $serverUrl .= isset($parts['path']) ? $parts['path'] : '';
        $serverUrl .= isset($parts['query']) ? '?' . $parts['query'] : '';

        return $serverUrl;
    }

    /**
     * Test if server URL is accessible
     *
     * @return bool
     */
    public function isAccessible()
    {
        // Check connection to server is possible
        $seleniumConnection = @fsockopen(
            $this->serverUrlParts['host'],
            $this->serverUrlParts['port'],
            $connectionErrorNo,
            $connectionError,
            5
        );
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
     * @return bool
     */
    public function isSeleniumServer()
    {
        // Check server properly responds to http requests
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $responseData = @file_get_contents($this->getServerUrl() . self::STATUS_ENDPOINT, false, $context);

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

    /**
     * Is current server cloud service?
     *
     * @return bool
     */
    public function isCloudService()
    {
        return $this->cloudService;
    }

    /**
     * @param $seleniumServerUrl
     * @return array URL parts. Scheme, host and port are always non-empty.
     */
    protected function parseServerUrl($seleniumServerUrl)
    {
        $urlParts = parse_url($seleniumServerUrl);

        if (!is_array($urlParts)|| empty($urlParts['scheme']) || empty($urlParts['host'])) {
            throw new \RuntimeException(sprintf('Provided Selenium server URL "%s" is invalid', $seleniumServerUrl));
        }

        if ($this->detectCloudService($urlParts['host'])) {
            $this->cloudService = true;
        }

        if (empty($urlParts['port'])) {
            if ($this->cloudService) {
                $urlParts['port'] = self::DEFAULT_PORT_CLOUD_SERVICE;
            } else {
                $urlParts['port'] = self::DEFAULT_PORT;
            }
        }

        return $urlParts;
    }

    /**
     * @param string $host
     * @return bool
     */
    protected function detectCloudService($host)
    {
        if (strpos($host, 'saucelabs.com') !== false || strpos($host, 'browserstack.com') !== false) {
            return true;
        }

        return false;
    }
}
