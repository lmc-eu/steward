<?php

namespace Lmc\Steward\Publisher;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\ConfigHelper;
use Lmc\Steward\Test\AbstractTestCaseBase;
use Lmc\Steward\WebDriver\NullWebDriver;
use phpmock\phpunit\PHPMock;

/**
 * @covers Lmc\Steward\Publisher\SauceLabsPublisher
 */
class SauceLabsPublisherTest extends \PHPUnit_Framework_TestCase
{
    use PHPMock;

    /** @var SauceLabsPublisher */
    protected $publisher;
    /** @var \PHPUnit_Framework_MockObject_MockObject|AbstractTestCaseBase */
    protected $testInstanceMock;

    public function setUp()
    {
        $configValues = ConfigHelper::getDummyConfig();
        $configValues['SERVER_URL'] = 'http://username:pass@ondemand.saucelabs.com:80';
        ConfigHelper::setEnvironmentVariables($configValues);
        ConfigHelper::unsetConfigInstance();

        $this->publisher = new SauceLabsPublisher();
        $this->testInstanceMock = $this->getMockBuilder(AbstractTestCaseBase::class)
            ->getMock();
    }

    public function testShouldDoNothingWhenPublishingTestcaseResults()
    {
        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_init');
        $curlInitMock->expects($this->never());

        $this->publisher->publishResults('testCaseName', AbstractPublisher::TEST_STATUS_DONE);
    }

    public function testShouldDoNothingIfTestStatusIsNotDone()
    {
        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_init');
        $curlInitMock->expects($this->never());

        $this->publisher->publishResult(
            'testCaseFoo',
            'testBar',
            $this->testInstanceMock,
            AbstractPublisher::TEST_STATUS_STARTED
        );
    }

    public function testShouldDoNothingIfTestResultIsSkippedOrIncomplete()
    {
        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_init');
        $curlInitMock->expects($this->never());

        $this->publisher->publishResult(
            'testCaseFoo',
            'testBar',
            $this->testInstanceMock,
            AbstractPublisher::TEST_STATUS_DONE,
            AbstractPublisher::TEST_RESULT_SKIPPED
        );

        $this->publisher->publishResult(
            'testCaseFoo',
            'testBar',
            $this->testInstanceMock,
            AbstractPublisher::TEST_STATUS_DONE,
            AbstractPublisher::TEST_RESULT_INCOMPLETE
        );
    }

    public function testShouldDoNothingIfTestContainsInstanceOfNullWebdriver()
    {
        $this->testInstanceMock->wd = new NullWebDriver();

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_init');
        $curlInitMock->expects($this->never());

        $this->publisher->publishResult(
            'testCaseFoo',
            'testBar',
            $this->testInstanceMock,
            AbstractPublisher::TEST_STATUS_DONE
        );
    }

    public function testShouldPublishTestResult()
    {
        $this->testInstanceMock->wd = $this->getMockBuilder(RemoteWebDriver::class)
            ->disableOriginalConstructor()
            ->getMock();

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_init');
        $curlInitMock->expects($this->once())
            ->with('https://saucelabs.com/rest/v1/username/jobs/');

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_setopt');
        $curlInitMock->expects($this->any());

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
            AbstractPublisher::TEST_RESULT_FAILED
        );
    }

    public function testShouldThrowExceptionIfPublishToApiFailed()
    {
        $this->testInstanceMock->wd = $this->getMockBuilder(RemoteWebDriver::class)
            ->disableOriginalConstructor()
            ->getMock();

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_init');
        $curlInitMock->expects($this->any());

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_setopt');
        $curlInitMock->expects($this->any());

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_exec');
        $curlInitMock->expects($this->once());

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_errno');
        $curlInitMock->expects($this->once())
            ->willReturn(333);

        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_error');
        $curlInitMock->expects($this->once())
            ->willReturn('omg wtf');

        $this->setExpectedException(
            \Exception::class,
            'Error publishing results of test testBar to SauceLabs API: omg wtf'
        );

        $this->publisher->publishResult(
            'testCaseFoo',
            'testBar',
            $this->testInstanceMock,
            AbstractPublisher::TEST_STATUS_DONE,
            AbstractPublisher::TEST_RESULT_PASSED
        );
    }
}
