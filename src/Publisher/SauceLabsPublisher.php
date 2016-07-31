<?php

namespace Lmc\Steward\Publisher;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Selenium\SeleniumServerAdapter;
use Lmc\Steward\Test\AbstractTestCaseBase;

/**
 * Publish test results to SauceLabs API
 */
class SauceLabsPublisher extends AbstractPublisher
{
    const API_URL = 'https://saucelabs.com/rest/v1';

    public function publishResults(
        $testCaseName,
        $status,
        $result = null,
        \DateTimeInterface $startDate = null,
        \DateTimeInterface $endDate = null
    ) {
        // we publish only result for each separate test using publishResult()
    }

    public function publishResult(
        $testCaseName,
        $testName,
        \PHPUnit_Framework_Test $testInstance,
        $status,
        $result = null,
        $message = null
    ) {
        if ($status != self::TEST_STATUS_DONE) {
            return;
        }
        if ($result == self::TEST_RESULT_SKIPPED || $result == self::TEST_RESULT_INCOMPLETE) {
            return;
        }

        if (!$testInstance instanceof AbstractTestCaseBase || !$testInstance->wd instanceof RemoteWebDriver) {
            return;
        }

        if ($result == self::TEST_RESULT_PASSED) {
            $sauceResult = true;
        } else {
            $sauceResult = false;
        }

        $serverUrl = ConfigProvider::getInstance()->serverUrl;
        $serverUrlParts = (new SeleniumServerAdapter($serverUrl))->getServerUrlParts();

        $endpointUrl = sprintf(
            '%s/%s/jobs/%s',
            self::API_URL,
            $serverUrlParts['user'],
            $testInstance->wd->getSessionID()
        );

        $curl = $this->initCurl(
            $endpointUrl,
            $serverUrlParts['user'] . ':' . $serverUrlParts['pass'],
            ['passed' => $sauceResult]
        );

        curl_exec($curl);

        if (curl_errno($curl)) {
            throw new \Exception(
                sprintf('Error publishing results of test %s to SauceLabs API: %s', $testName, curl_error($curl))
            );
        }

        curl_close($curl);
    }

    /**
     * @param string $url
     * @param string $auth
     * @param mixed $data Data to be sent
     * @return resource
     */
    private function initCurl($url, $auth, $data)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_USERPWD, $auth);

        $postData = json_encode($data);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

        return $curl;
    }
}
