<?php

namespace Lmc\Steward\Publisher;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\Test\AbstractTestCaseBase;

/**
 * Abstract publisher for cloud services with HTTP API to store test results
 */
abstract class AbstractCloudPublisher extends AbstractPublisher
{
    /** @var string Content type of the request */
    const CONTENT_TYPE = 'application/x-www-form-urlencoded';

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

        $url = $this->getEndpointUrl($testInstance);
        $curl = $this->initCurl($url, $this->getAuth());

        curl_setopt(
            $curl,
            CURLOPT_POSTFIELDS,
            $this->getData($testCaseName, $testName, $testInstance, $status, $result, $message)
        );

        curl_exec($curl);

        if (curl_errno($curl)) {
            throw new \Exception(
                sprintf('Error publishing results of test %s to API "%s": %s', $testName, $url, curl_error($curl))
            );
        }

        curl_close($curl);
    }

    /**
     * @param string $url
     * @param string $auth
     * @return resource
     */
    protected function initCurl($url, $auth)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: ' . static::CONTENT_TYPE]);
        curl_setopt($curl, CURLOPT_USERPWD, $auth);

        return $curl;
    }

    /**
     * Get URL of the API endpoint where to put result data
     *
     * @param AbstractTestCaseBase $testInstance
     * @return string
     */
    abstract protected function getEndpointUrl(AbstractTestCaseBase $testInstance);

    /**
     * Get authentication string
     *
     * @return string
     */
    abstract protected function getAuth();

    /**
     * Get data to be send to the API
     *
     * @param string $testCaseName
     * @param string $testName
     * @param AbstractTestCaseBase $testInstance
     * @param string $status
     * @param string $result
     * @param string $message
     * @return mixed
     */
    abstract protected function getData(
        $testCaseName,
        $testName,
        AbstractTestCaseBase $testInstance,
        $status,
        $result = null,
        $message = null
    );
}
