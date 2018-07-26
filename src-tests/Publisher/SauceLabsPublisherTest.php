<?php declare(strict_types=1);

namespace Lmc\Steward\Publisher;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\ConfigHelper;

/**
 * @covers \Lmc\Steward\Publisher\AbstractCloudPublisher
 * @covers \Lmc\Steward\Publisher\SauceLabsPublisher
 */
class SauceLabsPublisherTest extends AbstractCloudPublisherTestCase
{
    protected function setUp(): void
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['SERVER_URL'] = 'http://username:pass@ondemand.saucelabs.com:80';
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();

        $this->publisher = new SauceLabsPublisher();

        parent::setUp();
    }

    /**
     * @dataProvider provideTestResult
     */
    public function testShouldPublishTestResult(string $testResult, ?string $message, string $expectedData): void
    {
        $this->testInstanceMock->wd = $this->createMock(RemoteWebDriver::class);

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_init');
        $curlInitMock->expects($this->once())
            ->with('https://saucelabs.com/rest/v1/username/jobs/');

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_setopt');
        $curlInitMock->expects($this->at(4))
            ->with($this->anything(), CURLOPT_POSTFIELDS, $expectedData);

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_exec');
        $curlInitMock->expects($this->once());

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_errno');
        $curlInitMock->expects($this->once())
            ->willReturn(false);

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_close');
        $curlInitMock->expects($this->once());

        $this->publisher->publishResult(
            'testCaseFoo',
            'testBar',
            $this->testInstanceMock,
            AbstractPublisher::TEST_STATUS_DONE,
            $testResult,
            $message
        );
    }

    /**
     * @return array[]
     */
    public function provideTestResult(): array
    {
        return [
            'Passed test' => [AbstractPublisher::TEST_RESULT_PASSED, null, '{"passed":true}'],
            'Failed test' => [AbstractPublisher::TEST_RESULT_FAILED, null, '{"passed":false}'],
            'Failed test with message' => [
                AbstractPublisher::TEST_RESULT_FAILED,
                'Error occurred',
                '{"passed":false,"custom-data":{"message":"Error occurred"}}',
            ],
            'Broken test' => [AbstractPublisher::TEST_RESULT_BROKEN, null, '{"passed":false}'],
        ];
    }
}
