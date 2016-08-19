<?php

namespace Lmc\Steward\Publisher;

use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Lmc\Steward\Test\AbstractTestCaseBase;

/**
 * Publish test results to TestingBot API
 */
class TestingBotPublisher extends AbstractCloudPublisher
{
    const API_URL = 'https://api.testingbot.com/v1';

    protected function getEndpointUrl(AbstractTestCaseBase $testInstance)
    {
        return sprintf('%s/tests/%s', self::API_URL, $testInstance->wd->getSessionID());
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
        AbstractTestCaseBase $testInstance,
        $status,
        $result = null,
        $message = null
    ) {
        if ($result == self::TEST_RESULT_PASSED) {
            $resultToPublish = 1;
        } else {
            $resultToPublish = 0;
        }

        $data = ['test[success]' => $resultToPublish];

        if (!empty($message)) {
            $data['test[status_message]'] = $message;
        }

        return http_build_query($data, null, '&');
    }
}
