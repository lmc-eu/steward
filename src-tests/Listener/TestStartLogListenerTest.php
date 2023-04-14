<?php declare(strict_types=1);

namespace Lmc\Steward\Listener;

use Lmc\Steward\MockAbstractTestCaseWithNameTrait;
use Lmc\Steward\Test\AbstractTestCaseTest;
use PHPUnit\Framework\TestCase;

class TestStartLogListenerTest extends TestCase
{
    use MockAbstractTestCaseWithNameTrait;

    public function testShouldLogTestNameToOutputOnTestStart(): void
    {
        $listener = new TestStartLogListener();

        $testCase = $this->getAbstractTestCaseMock('MockedTest', 'testFooBar');

        $listener->startTest($testCase);

        $this->expectOutputRegex(
            '/^' . AbstractTestCaseTest::EXPECTED_TIMESTAMP_PATTERN
            . ' --- Starting execution of test "testFooBar" ---/'
        );
    }
}
