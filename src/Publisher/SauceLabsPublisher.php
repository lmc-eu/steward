<?php declare(strict_types=1);

namespace Lmc\Steward\Publisher;

use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * Publish test results to SauceLabs API
 */
class SauceLabsPublisher extends AbstractCloudPublisher
{
    protected const API_URL = 'https://saucelabs.com/rest/v1';
    protected const CONTENT_TYPE = 'application/json';

    protected function getEndpointUrl(AbstractTestCase $testInstance): string
    {
        $serverUrl = ConfigProvider::getInstance()->serverUrl;
        $serverUrlParts = (new SeleniumServerAdapter($serverUrl))->getServerUrlParts();

        return sprintf('%s/%s/jobs/%s', self::API_URL, $serverUrlParts['user'], $testInstance->wd->getSessionID());
    }

    protected function getAuth(): string
    {
        $serverUrl = ConfigProvider::getInstance()->serverUrl;
        $serverUrlParts = (new SeleniumServerAdapter($serverUrl))->getServerUrlParts();

        return $serverUrlParts['user'] . ':' . $serverUrlParts['pass'];
    }

    protected function getData(
        string $testCaseName,
        string $testName,
        AbstractTestCase $testInstance,
        string $status,
        string $result = null,
        string $message = null
    ): string {
        $data = ['passed' => ($result === self::TEST_RESULT_PASSED)];

        if (!empty($message)) {
            $data['custom-data'] = ['message' => $message];
        }

        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
