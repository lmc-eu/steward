<?php

namespace Lmc\Steward\Publisher;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\Test\AbstractTestCaseBase;
use Lmc\Steward\WebDriver\NullWebDriver;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

abstract class AbstractCloudPublisherTestCase extends TestCase
{
    use PHPMock;

    /** @var AbstractCloudPublisher */
    protected $publisher;
    /** @var \PHPUnit_Framework_MockObject_MockObject|AbstractTestCaseBase */
    protected $testInstanceMock;

    public function setUp()
    {
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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp(
            '/Error publishing results of test testBar to API "https:\/\/.*": omg wtf/'
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
