<?php declare(strict_types=1);

namespace Lmc\Steward\Timeline;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Lmc\Steward\Timeline\TimelineDataBuilder
 */
class TimelineDataBuilderTest extends TestCase
{
    public function testShouldNotIncludeStartedTestsInTimelineData(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/Fixtures/results-with-only-started-tests.xml');
        $builder = new TimelineDataBuilder($xml);

        $this->assertSame([], $builder->buildTimelineGroups());
        $this->assertSame([], $builder->buildTimelineItems());
    }

    public function testShouldAppendUnknownGroupIfExecutorOfAnyTestIsNotDefined(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/Fixtures/results-with-unknown-executor.xml');
        $builder = new TimelineDataBuilder($xml);

        $this->assertEquals(
            [
                [
                    'id' => 0,
                    'content' => 'http://127.0.0.1:5555',
                    'title' => 'http://127.0.0.1:5555',
                ],
                [
                    'id' => 'unknown',
                    'content' => 'unknown',
                    'title' => 'unknown',
                ],
            ],
            $builder->buildTimelineGroups()
        );
    }

    public function testShouldBuildGroupsForEachUniqueExecutor(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/Fixtures/results.xml');
        $builder = new TimelineDataBuilder($xml);

        $this->assertEquals(
            [
                [
                    'id' => 0,
                    'content' => 'http://127.0.0.1:5555',
                    'title' => 'http://127.0.0.1:5555',
                ],
                [
                    'id' => 1,
                    'content' => 'http://127.0.0.1:5556',
                    'title' => 'http://127.0.0.1:5556',
                ],
                [
                    'id' => 2,
                    'content' => 'http://127.0.0.1:5558',
                    'title' => 'http://127.0.0.1:5558',
                ],
                [
                    'id' => 'unknown',
                    'content' => 'unknown',
                    'title' => 'unknown',
                ],
            ],
            $builder->buildTimelineGroups()
        );
    }

    public function testShouldBuildTimelineItemsForAllDoneTests(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/Fixtures/results.xml');
        $builder = new TimelineDataBuilder($xml);

        $this->assertEquals(
            [
                [
                    'group' => 2,
                    'content' => 'testBarFirstPassed',
                    'title' => 'Foo\\BarPassedTest::testBarFirstPassed<br>Duration: 333 sec | Result: passed',
                    'start' => '2016-12-29T12:56:29',
                    'end' => '2016-12-29T13:02:02',
                    'className' => 'passed',
                ],
                [
                    'group' => 0,
                    'content' => 'testBarSecondPassed',
                    'title' => 'Foo\\BarPassedTest::testBarSecondPassed<br>Duration: 66 sec | Result: passed',
                    'start' => '2016-12-29T12:57:05',
                    'end' => '2016-12-29T12:58:11',
                    'className' => 'passed',
                ],
                [
                    'group' => 'unknown',
                    'content' => 'testBarThirdWithoutExecutor',
                    'title' => 'Foo\\BarPassedTest::testBarThirdWithoutExecutor<br>Duration: 1 sec | Result: passed',
                    'start' => '2016-12-29T12:56:28',
                    'end' => '2016-12-29T12:56:29',
                    'className' => 'passed',
                ],
                [
                    'group' => 1,
                    'content' => 'testBarFirstPassed',
                    'title' => 'Foo\\BazFailedTest::testBarFirstPassed<br>Duration: 10 sec | Result: passed',
                    'start' => '2016-12-29T12:57:05',
                    'end' => '2016-12-29T12:57:15',
                    'className' => 'passed',
                ],
                [
                    'group' => 2,
                    'content' => 'testBazSecondBroken',
                    'title' => 'Foo\\BazFailedTest::testBazSecondBroken<br>Duration: 175 sec | Result: broken',
                    'start' => '2017-01-02T03:02:08',
                    'end' => '2017-01-02T03:05:03',
                    'className' => 'broken',
                ],
            ],
            $builder->buildTimelineItems()
        );
    }

    public function testShouldProcessEmptyResults(): void
    {
        $xml = simplexml_load_string('<?xml version="1.0" encoding="utf-8"?><testcases/>');
        $builder = new TimelineDataBuilder($xml);

        $this->assertSame([], $builder->buildTimelineGroups());
        $this->assertSame([], $builder->buildTimelineItems());
    }
}
