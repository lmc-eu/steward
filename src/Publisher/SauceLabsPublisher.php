<?php

namespace Lmc\Steward\Publisher;

use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * Publish test results to SauceLabs API
 */
class SauceLabsPublisher extends AbstractCloudPublisher
{
    const API_URL = 'https://saucelabs.com/rest/v1';
    const CONTENT_TYPE = 'application/json';

    protected function getEndpointUrl(AbstractTestCase $testInstance)
    {
        $serverUrl = ConfigProvider::getInstance()->serverUrl;
        $serverUrlParts = (new SeleniumServerAdapter($serverUrl))->getServerUrlParts();

        return sprintf('%s/%s/jobs/%s', self::API_URL, $serverUrlParts['user'], $testInstance->wd->getSessionID());
    }

    protected function getAuth()
    {
        $serverUrl = ConfigProvider::getInstance()->serverUrl;
        $serverUrlParts = (new SeleniumServerAdapter($serverUrl))->getServerUrlParts();

        return $serverUrlParts['user'] . ':' . $serverUrlParts['pass'];
    }

    protected function getData(
        $testCaseName,
        $testName,
        AbstractTestCase $testInstance,
        $status,
        $result = null,
        $message = null
    ) {
        $data = ['passed' => ($result == self::TEST_RESULT_PASSED)];

        if (!empty($message)) {
            $data['custom-data'] = ['message' => $message];
        }

        return json_encode($data);
    }
}
