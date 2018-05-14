<?php declare(strict_types=1);

namespace Lmc\Steward\Publisher;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\Test\AbstractTestCase;
use Lmc\Steward\WebDriver\NullWebDriver;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

abstract class AbstractCloudPublisherTestCase extends TestCase
{
    use PHPMock;

    /** @var AbstractCloudPublisher */
    protected $publisher;
    /** @var MockObject|AbstractTestCase */
    protected $testInstanceMock;

    protected function setUp(): void
    {
        $this->testInstanceMock = $this->createMock(AbstractTestCase::class);
    }

    public function testShouldDoNothingWhenPublishingTestcaseResults(): void
    {
        $curlInitMock = $this->getFunctionMock(__NAMESPACE__, 'curl_init');
        $curlInitMock->expects($this->never());

        $this->publisher->publishResults('testCaseName', AbstractPublisher::TEST_STATUS_DONE);
    }

    public function testShouldDoNothingIfTestStatusIsNotDone(): void
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

    public function testShouldDoNothingIfTestResultIsSkippedOrIncomplete(): void
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

    public function testShouldDoNothingIfTestContainsInstanceOfNullWebdriver(): void
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

    public function testShouldThrowExceptionIfPublishToApiFailed(): void
    {
        $this->testInstanceMock->wd = $this->createMock(RemoteWebDriver::class);

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

        $this->expectException(\RuntimeException::class);
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
