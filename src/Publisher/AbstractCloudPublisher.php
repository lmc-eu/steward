<?php declare(strict_types=1);

namespace Lmc\Steward\Publisher;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\Test\AbstractTestCase;
use PHPUnit\Framework\Test;

/**
 * Abstract publisher for cloud services with HTTP API to store test results
 */
abstract class AbstractCloudPublisher extends AbstractPublisher
{
    /** @var string Content type of the request */
    protected const CONTENT_TYPE = 'application/x-www-form-urlencoded';

    public function publishResults(
        string $testCaseName,
        string $status,
        string $result = null,
        \DateTimeInterface $testCaseStartDate = null,
        \DateTimeInterface $testCaseEndDate = null
    ): void {
        // we publish only result for each separate test using publishResult()
    }

    public function publishResult(
        string $testCaseName,
        string $testName,
        Test $testInstance,
        string $status,
        string $result = null,
        string $message = null
    ): void {
        if ($status !== self::TEST_STATUS_DONE) {
            return;
        }
        if ($result === self::TEST_RESULT_SKIPPED || $result === self::TEST_RESULT_INCOMPLETE) {
            return;
        }

        if (!$testInstance instanceof AbstractTestCase || !$testInstance->wd instanceof RemoteWebDriver) {
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
            throw new \RuntimeException(
                sprintf('Error publishing results of test %s to API "%s": %s', $testName, $url, curl_error($curl))
            );
        }

        curl_close($curl);
    }

    /**
     * @return resource|\CurlHandle|false
     */
    protected function initCurl(string $url, string $auth)
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
     */
    abstract protected function getEndpointUrl(AbstractTestCase $testInstance): string;

    /**
     * Get authentication string
     */
    abstract protected function getAuth(): string;

    /**
     * Get data to be send to the API
     */
    abstract protected function getData(
        string $testCaseName,
        string $testName,
        AbstractTestCase $testInstance,
        string $status,
        string $result = null,
        string $message = null
    ): string;
}
