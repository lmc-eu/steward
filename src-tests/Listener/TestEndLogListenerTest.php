<?php declare(strict_types=1);

namespace Lmc\Steward\Listener;

use Lmc\Steward\MockAbstractTestCaseWithNameTrait;
use Lmc\Steward\Test\AbstractTestCase;
use Lmc\Steward\Test\AbstractTestCaseTest;
use PHPUnit\Framework\TestCase;

class TestEndLogListenerTest extends TestCase
{
    use MockAbstractTestCaseWithNameTrait;

    public function testShouldLogTestNameToOutputOnTestEnd(): void
    {
        $listener = new TestEndLogListener();

        /** @var AbstractTestCase|\PHPUnit_Framework_MockObject_MockObject $testCase */
        $testCase = $this->getAbstractTestCaseMock('MockedTest', 'testFooBar');

        $listener->endTest($testCase, 1);

        $this->assertRegExp(
            '/^' . AbstractTestCaseTest::EXPECTED_TIMESTAMP_PATTERN
            . ' --- Finished execution of test "testFooBar" ---/',
            $testCase->getActualOutput()
        );
    }
}
