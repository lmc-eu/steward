<?php declare(strict_types=1);

namespace Lmc\Steward\Selenium;

/**
 * Selenium server API adapter, used mainly to check connection (communication with Selenium server handles the
 * WebDriverCommandExecutor, not this adapter).
 */
class SeleniumServerAdapter
{
    public const CLOUD_SERVICE_SAUCELABS = 'saucelabs';
    public const CLOUD_SERVICE_BROWSERSTACK = 'browserstack';
    public const CLOUD_SERVICE_TESTINGBOT = 'testingbot';

    protected const STATUS_ENDPOINT = '/status';
    protected const TESTSESSION_ENDPOINT = '/grid/api/testsession';
    protected const HUB_ENDPOINT = '/wd/hub';
    protected const DEFAULT_PORT = 4444;
    protected const DEFAULT_PORT_CLOUD_SERVICE = 80;
    protected const DEFAULT_PORT_CLOUD_SERVICE_HTTPS = 443;

    /** @var array */
    protected $serverUrlParts;
    /** @var string */
    protected $lastError = '';
    /** @var string */
    protected $cloudService;

    public function __construct(string $serverUrl)
    {
        $this->serverUrlParts = $this->parseServerUrl($serverUrl);
    }

    /**
     * Get description of last error
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getServerUrlParts(): array
    {
        return $this->serverUrlParts;
    }

    public function getServerUrl(): string
    {
        $parts = $this->serverUrlParts;

        $serverUrl = $parts['scheme'] . '://';
        $serverUrl .= $parts['user'] ?? '';
        $serverUrl .= isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $serverUrl .= (isset($parts['user']) || isset($parts['pass'])) ? '@' : '';
        $serverUrl .= $parts['host'] . ':' . $parts['port'];
        $serverUrl .= isset($parts['path']) ? rtrim($parts['path'], '/') : '';
        $serverUrl .= isset($parts['query']) ? '?' . $parts['query'] : '';

        return $serverUrl;
    }

    /**
     * Test if server URL is accessible and we can connected there
     */
    public function isAccessible(): bool
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
            $this->lastError = $connectionError ?? 'unknown connection error';

            return false;
        }
        fclose($seleniumConnection);

        return true;
    }

    /**
     * Test if remote server is really a Selenium server and is ready to accept connection
     */
    public function isSeleniumServer(): bool
    {
        // Check server properly responds to http requests
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $responseData = @file_get_contents($this->getServerUrl() . self::STATUS_ENDPOINT, false, $context);

        if (!$responseData) {
            $this->lastError = 'error reading server response';

            return false;
        }

        try {
            $decodedData = json_decode($responseData, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->lastError = 'error parsing server JSON response (' . $e->getMessage() . ')';

            return false;
        }

        $this->cloudService = $this->detectCloudServiceByStatus($decodedData);

        return true;
    }

    /**
     * Get name of the cloud service we are connected to.
     *
     * @return string Cloud service identifier; empty string if no cloud service detected
     */
    public function getCloudService(): string
    {
        // If cloud service value is not yet initialized, attempt to connect to the server first
        if ($this->cloudService === null) {
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
     */
    public function getSessionExecutor(string $sessionId): string
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 1]]);

        // Selenium server URL ends with /wd/hub. But the endpoint to get the session info is available on URL
        // which starts from the same root path as the /wd/hub - thus we must remove this path.
        $endpointUrl = $this->removeHubEndpointPathIfPresent($this->getServerUrl()) . self::TESTSESSION_ENDPOINT;

        $responseData = @file_get_contents($endpointUrl . '?session=' . $sessionId, false, $context);

        if (!$responseData) {
            return '';
        }

        try {
            $responseJson = json_decode($responseData, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return '';
        }

        if (empty($responseJson->proxyId)) {
            return '';
        }

        return $responseJson->proxyId;
    }

    /**
     * @return array URL parts. Scheme, host and port are always non-empty.
     */
    protected function parseServerUrl(string $seleniumServerUrl): array
    {
        $urlParts = parse_url($seleniumServerUrl);

        if (!is_array($urlParts) || empty($urlParts['scheme']) || empty($urlParts['host'])) {
            throw new \RuntimeException(sprintf('Provided Selenium server URL "%s" is invalid', $seleniumServerUrl));
        }

        if (empty($urlParts['port'])) {
            $urlParts['port'] = $this->guessPort($urlParts['host'], $urlParts['scheme']);
        }

        return $urlParts;
    }

    /**
     * Guess port for given service
     */
    protected function guessPort(string $host, string $scheme): int
    {
        if ($scheme === 'https') {
            return self::DEFAULT_PORT_CLOUD_SERVICE_HTTPS;
        }

        foreach (['saucelabs.com', 'browserstack.com', 'testingbot.com'] as $knownCloudHost) {
            if (mb_strpos($host, $knownCloudHost) !== false) {
                return self::DEFAULT_PORT_CLOUD_SERVICE;
            }
        }

        return self::DEFAULT_PORT;
    }

    protected function removeHubEndpointPathIfPresent(string $path): string
    {
        return preg_replace(
            '/^(.*)(' . preg_quote(self::HUB_ENDPOINT, '/') . '\/?)$/',
            '$1',
            $path
        );
    }

    /**
     * Detect cloud service using server status response
     *
     * @param object $responseData
     */
    private function detectCloudServiceByStatus($responseData): string
    {
        if (isset($responseData->value, $responseData->value->build, $responseData->value->build->version)) {
            if ($responseData->value->build->version === 'Sauce Labs') {
                return self::CLOUD_SERVICE_SAUCELABS;
            } elseif ($responseData->value->build->version === 'TestingBot') {
                return self::CLOUD_SERVICE_TESTINGBOT;
            } elseif (!isset($responseData->class) && !isset($responseData->value->ready)) {
                return self::CLOUD_SERVICE_BROWSERSTACK;
            }
        }

        return '';
    }
}
