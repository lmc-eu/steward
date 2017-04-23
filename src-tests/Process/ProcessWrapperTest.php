<?php

namespace Lmc\Steward\Process;

use Assert\InvalidArgumentException;
use Lmc\Steward\Publisher\XmlPublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ProcessWrapperTest extends TestCase
{
    public function testShouldWrapGivenProcess()
    {
        $process = new Process('');

        $wrapper = new ProcessWrapper($process, 'ClassName');

        $this->assertSame($process, $wrapper->getProcess());
        $this->assertSame('ClassName', $wrapper->getClassName());
        $this->assertSame(ProcessWrapper::PROCESS_STATUS_QUEUED, $wrapper->getStatus());
        $this->assertFalse($wrapper->isDelayed());
    }

    /**
     * @dataProvider provideDelay
     * @param float $delay
     */
    public function testShouldSetDelayForTheProcess($delay)
    {
        $wrapper = new ProcessWrapper(new Process(''), 'Foo');
        $wrapper->setDelay('Bar', $delay);

        $this->assertTrue($wrapper->isDelayed());
        $this->assertEquals($delay, $wrapper->getDelayMinutes(), '', 0.001);
    }

    /**
     * @return array[]
     */
    public function provideDelay()
    {
        return [
            'integer value' => [1],
            'float value' => [3.33],
            'zero value (should also set the Process as delayed)' => [0],
        ];
    }

    /**
     * @dataProvider provideInvalidDelay
     * @param mixed $delay
     * @param string $expectedExceptionMessage
     */
    public function testShouldAcceptOnlyNumbersGreaterThanOrEqualToZeroAsDelay($delay, $expectedExceptionMessage)
    {
        $wrapper = new ProcessWrapper(new Process(''), 'Foo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $wrapper->setDelay('Bar', $delay);
    }

    /**
     * @return array[]
     */
    public function provideInvalidDelay()
    {
        return [
            'negative value' => [
                -5,
                'Delay defined in testcase "Foo" must be greater than or equal 0, but "-5" was given',
            ],
            'string value' => [
                'omg',
                'Delay defined in testcase "Foo" must be greater than or equal 0, but "omg" was given',
            ],
            'empty value' => [
                '',
                'Delay defined in testcase "Foo" must be greater than or equal 0, but "" was given',
            ],
        ];
    }

    public function testShouldFailIfDependencyWasDefinedButWithoutDelay()
    {
        $wrapper = new ProcessWrapper(new Process(''), 'Foo');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Testcase "Foo" should run after "Bar", but no delay was defined');

        $wrapper->setDelay('Bar', null);
    }

    /**
     * @dataProvider provideProcessResult
     * @param int $exitCode
     * @param string $expectedResult
     */
    public function testShouldResolveAndStoreResultOfDoneProcess($exitCode, $expectedResult)
    {
        $processMock = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $processMock->expects($this->once())
            ->method('getExitCode')
            ->willReturn($exitCode);

        $wrapper = new ProcessWrapper($processMock, 'DoneTest');

        $wrapper->setStatus(ProcessWrapper::PROCESS_STATUS_DONE);

        $this->assertSame(ProcessWrapper::PROCESS_STATUS_DONE, $wrapper->getStatus());
        $this->assertSame($expectedResult, $wrapper->getResult());
        $this->assertNotEmpty($wrapper->getFinishedTime());
    }

    /**
     * @return array[]
     */
    public function provideProcessResult()
    {
        return [
            // $exitCode, $expectedResult
            'Testcase succeeded' => [\PHPUnit_TextUI_TestRunner::SUCCESS_EXIT, ProcessWrapper::PROCESS_RESULT_PASSED],
            'Exception thrown from PHPUnit' =>
                [\PHPUnit_TextUI_TestRunner::EXCEPTION_EXIT, ProcessWrapper::PROCESS_RESULT_FAILED],
            'Some test failed' =>
                [\PHPUnit_TextUI_TestRunner::FAILURE_EXIT, ProcessWrapper::PROCESS_RESULT_FAILED],
            'PHP fatal error' => [255, ProcessWrapper::PROCESS_RESULT_FATAL],
            'Process was killed' => [9, ProcessWrapper::PROCESS_RESULT_FATAL],
            'Process was terminated' => [9, ProcessWrapper::PROCESS_RESULT_FATAL],
            'Unrecognized exit error code should mark result as failed' => [66, ProcessWrapper::PROCESS_RESULT_FAILED],
            'None exit code (null) should mark result as failed' => [null, ProcessWrapper::PROCESS_RESULT_FAILED],
        ];
    }

    public function testShouldNotStoreResultAndTimeOfWhenSettingOtherThanDoneStatus()
    {
        $preparedTest = new ProcessWrapper(new Process(''), 'PreparedTest');
        $preparedTest->setStatus(ProcessWrapper::PROCESS_STATUS_PREPARED);

        $this->assertNull($preparedTest->getResult());
        $this->assertNull($preparedTest->getFinishedTime());
    }

    public function testShouldFailIfWrongProcessStatusGiven()
    {
        $wrapper = new ProcessWrapper(new Process(''), 'Foo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Value "WrongStatus" is not an element of the valid values: prepared, queued, done'
        );

        $wrapper->setStatus('WrongStatus');
    }

    public function testShouldPublishProcessStatusWhenInitializedAndWhenStatusWasSet()
    {
        $publisherMock = $this->getMockBuilder(XmlPublisher::class)
            ->disableOriginalConstructor()
            ->getMock();

        $publisherMock->expects($this->at(0))
            ->method('publishResults')
            ->with(
                'FooClassName',
                ProcessWrapper::PROCESS_STATUS_QUEUED,
                $this->identicalTo(null),
                $this->identicalTo(null),
                $this->identicalTo(null)
            );

        $publisherMock->expects($this->at(1))
            ->method('publishResults')
            ->with(
                'FooClassName',
                ProcessWrapper::PROCESS_STATUS_DONE,
                $this->identicalTo(ProcessWrapper::PROCESS_RESULT_FAILED),
                $this->identicalTo(null),
                $this->identicalTo(null)
            );

        $wrapper = new ProcessWrapper(new Process(''), 'FooClassName', $publisherMock);

        $wrapper->setStatus(ProcessWrapper::PROCESS_STATUS_DONE);
    }

    public function testShouldReturnErrorMessageIfProcessTimeoutIsDetected()
    {
        $processMock = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $processMock->expects($this->once())
            ->method('checkTimeout')
            ->willThrowException(
                new ProcessTimedOutException(
                    new Process('', null, null, null, 33),
                    ProcessTimedOutException::TYPE_GENERAL
                )
            );

        $wrapper = new ProcessWrapper($processMock, 'ClassName');

        $error = $wrapper->checkProcessTimeout();

        $this->assertContains(
            'Process for class "ClassName" exceeded the timeout of 33 seconds and was killed.',
            $error
        );
    }
}
