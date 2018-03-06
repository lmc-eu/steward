<?php declare(strict_types=1);

namespace Lmc\Steward\Publisher;

use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * Publish test results to TestingBot API
 */
class TestingBotPublisher extends AbstractCloudPublisher
{
    protected const API_URL = 'https://api.testingbot.com/v1';

    protected function getEndpointUrl(AbstractTestCase $testInstance): string
    {
        return sprintf('%s/tests/%s', self::API_URL, $testInstance->wd->getSessionID());
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
        if ($result === self::TEST_RESULT_PASSED) {
            $resultToPublish = 1;
        } else {
            $resultToPublish = 0;
        }

        $data = ['test[success]' => $resultToPublish];

        if (!empty($message)) {
            $data['test[status_message]'] = $message;
        }

        return http_build_query($data, '', '&');
    }
}
