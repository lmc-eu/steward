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
    const TESTSESSION_ENDPOINT = '/grid/api/testsession';
    const DEFAULT_PORT = 4444;
    const DEFAULT_PORT_CLOUD_SERVICE = 80;
    const CLOUD_SERVICE_SAUCELABS = 'saucelabs';
    const CLOUD_SERVICE_BROWSERSTACK = 'browserstack';
    const CLOUD_SERVICE_TESTINGBOT = 'testingbot';

    /** @var array */
    protected $serverUrlParts;
    /** @var string */
    protected $lastError;
    /** @var string */
    protected $cloudService = null;

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

        $this->cloudService = $this->detectCloudServiceByStatus(json_decode($responseData));

        return true;
    }

    /**
     * Get name of the cloud service we are connected to.
     *
     * @return string Cloud service identifier; empty string if no cloud service detected
     */
    public function getCloudService()
    {
        // If cloud service value is not yet initialized, attempt to connect to the server first
        if (is_null($this->cloudService)) {
            if (!$this->isSeleniumServer()) {
                throw new \RuntimeException(sprintf('Unable to connect to remote server: %s', $this->getLastError()));
            }
        }

        return $this->cloudService;
    }

    /**
     * Get URL of the concrete executor (node) of given session.
     * Note this is specific non-standard feature of Selenium server (and available only in grid mode), so empty string
     * will be returned if the server does not provide this functionality.
     *
     * @param string $sessionId
     * @return string
     */
    public function getSessionExecutor($sessionId)
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 1]]);
        $responseData = @file_get_contents(
            $this->getServerUrl() . self::TESTSESSION_ENDPOINT . '?session=' . $sessionId,
            false,
            $context
        );

        if (!$responseData) {
            return '';
        }

        $responseJson = json_decode($responseData);
        if (!$responseJson || empty($responseJson->proxyId)) {
            return '';
        }

        return $responseJson->proxyId;
    }

    /**
     * @param $seleniumServerUrl
     * @return array URL parts. Scheme, host and port are always non-empty.
     */
    protected function parseServerUrl($seleniumServerUrl)
    {
        $urlParts = parse_url($seleniumServerUrl);

        if (!is_array($urlParts) || empty($urlParts['scheme']) || empty($urlParts['host'])) {
            throw new \RuntimeException(sprintf('Provided Selenium server URL "%s" is invalid', $seleniumServerUrl));
        }

        if (empty($urlParts['port'])) {
            $urlParts['port'] = $this->guessPort($urlParts['host']);
        }

        return $urlParts;
    }

    /**
     * Guess port for given service
     *
     * @param string $host
     * @return int
     */
    protected function guessPort($host)
    {
        foreach (['saucelabs.com', 'browserstack.com', 'testingbot.com'] as $knownCloudHost) {
            if (mb_strpos($host, $knownCloudHost) !== false) {
                return self::DEFAULT_PORT_CLOUD_SERVICE;
            }
        }

        return self::DEFAULT_PORT;
    }

    /**
     * Detect cloud service using server status response
     *
     * @param object $responseData
     * @return string
     */
    private function detectCloudServiceByStatus($responseData)
    {
        if (isset($responseData->value, $responseData->value->build, $responseData->value->build->version)) {
            if ($responseData->value->build->version == 'Sauce Labs') {
                return self::CLOUD_SERVICE_SAUCELABS;
            } elseif ($responseData->value->build->version == 'TestingBot') {
                return self::CLOUD_SERVICE_TESTINGBOT;
            }

            if (!isset($responseData->class)) {
                return self::CLOUD_SERVICE_BROWSERSTACK;
            }
        }

        return '';
    }
}
