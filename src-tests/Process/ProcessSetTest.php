<?php

namespace Lmc\Steward\Process;

use Graphp\Algorithms\Tree\OutTree;
use Lmc\Steward\Process\Fixtures\MockOrderStrategy;
use Lmc\Steward\Publisher\XmlPublisher;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

class ProcessSetTest extends \PHPUnit_Framework_TestCase
{
    /** @var ProcessSet */
    protected $set;

    public function setUp()
    {
        $this->set = new ProcessSet();
    }

    public function testShouldBeCountable()
    {
        $this->assertCount(0, $this->set);
        $this->set->add(new Process(''), 'Foo');
        $this->assertCount(1, $this->set);
        $this->set->add(new Process(''), 'Bar');
        $this->set->add(new Process(''), 'Baz');
        $this->assertCount(3, $this->set);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Testcase "Foo" should run after "Bar", but no delay was defined
     */
    public function testShouldFailIfDependencyWasDefinedButWithoutDelay()
    {
        $this->set->add(new Process(''), 'Bar');
        $this->set->add(new Process(''), 'Foo', 'Bar');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Testcase with name "Foo\Bar" was already added
     */
    public function testShouldFailWhenAddingTestWithNonUniqueName()
    {
        $this->set->add(new Process(''), 'Foo\Bar');
        $this->set->add(new Process(''), 'Foo\Bar');
    }

    /**
     * @dataProvider delayProvider
     * @param mixed $delay
     * @param string|null $expectedExceptionMessage Null if no exception should be raised
     */
    public function testShouldAcceptOnlyGreaterThanOrEqualToZeroNumbersAsDelay($delay, $expectedExceptionMessage)
    {
        if ($expectedExceptionMessage !== null) {
            $this->setExpectedException('\InvalidArgumentException', $expectedExceptionMessage);
        }

        $this->set->add(new Process(''), 'Bar');
        $this->set->add(new Process(''), 'Foo', 'Bar', $delay);

        if ($expectedExceptionMessage === null) {
            // delay retrieved from the set is same as when process was added
            $this->assertEquals(
                $delay,
                $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED)['Foo']->delayMinutes,
                '',
                0.001
            );
        }
    }

    public function delayProvider()
    {
        return [
            // delay, expected exception (null if no exception should be raised)
            'integer value' => [1, null],
            'zero value' => [0, null],
            'float value' => [3.33, null],
            'negative value' => [
                -5,
                'Delay defined in testcase "Foo" must be greater than or equal 0, but "-5" was given'
            ],
            'string value' => [
                'omg',
                'Delay defined in testcase "Foo" must be greater than or equal 0, but "omg" was given'
            ],
        ];
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Testcase "Foo" has defined delay 5 minutes, but does not have defined the testcase to
     */
    public function testShouldFailIfDelayWasDefinedButNotTheDependentClass()
    {
        $this->set->add(new Process(''), 'Foo', null, 5);
    }

    public function testShouldSetNewlyAddedProcessAsQueued()
    {
        $this->set->add(new Process(''), 'Foo');
        $this->set->add(new Process(''), 'Bar');

        $this->assertCount(2, $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED));
        $this->assertCount(0, $this->set->get(ProcessSet::PROCESS_STATUS_DONE));
        $this->assertCount(0, $this->set->get(ProcessSet::PROCESS_STATUS_PREPARED));
    }

    public function testShouldPublishProcessWhenAdded()
    {
        $publisherMock = $this->getMockBuilder(XmlPublisher::class)
            ->disableOriginalConstructor()
            ->getMock();

        $publisherMock->expects($this->once())
            ->method('publishResults')
            ->with(
                'FooClassName',
                ProcessSet::PROCESS_STATUS_QUEUED,
                $this->identicalTo(null),
                $this->identicalTo(null),
                $this->identicalTo(null)
            );

        $set = new ProcessSet($publisherMock);
        $set->add(new Process(''), 'FooClassName');
    }

    public function testShouldAllowToDefinePublisherUsingSetter()
    {
        $set = new ProcessSet();

        $publisherMock = $this->getMockBuilder(XmlPublisher::class)
            ->disableOriginalConstructor()
            ->getMock();

        $publisherMock->expects($this->once())
            ->method('publishResults')
            ->with(
                'FooClassName',
                ProcessSet::PROCESS_STATUS_QUEUED,
                $this->identicalTo(null),
                $this->identicalTo(null),
                $this->identicalTo(null)
            );

        $set->setPublisher($publisherMock);
        $set->add(new Process(''), 'FooClassName');
    }

    public function testShouldAddAndGetProcess()
    {
        $this->set->add(new Process(''), 'Foo');
        $this->set->add(new Process(''), 'Baz', 'Foo', 5);

        $processes = $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED);
        $this->assertArrayHasKey('Foo', $processes);
        $this->assertArrayHasKey('Baz', $processes);

        $process = $processes['Baz'];
        $this->assertInstanceOf(\stdClass::class, $process);
        $this->assertEquals(ProcessSet::PROCESS_STATUS_QUEUED, $process->status);
        $this->assertInstanceOf(Process::class, $process->process);
        $this->assertEquals('Foo', $process->delayAfter);
        $this->assertEquals(5, $process->delayMinutes);
        $this->assertNull($process->finishedTime);
    }

    public function testShouldSetDefinedProcessStatus()
    {
        $this->set->add(new Process(''), 'DoneTest1');
        $this->set->add(new Process(''), 'DoneTest2');
        $this->set->add(new Process(''), 'PreparedTest');

        // all processes are queued by default
        $this->assertCount(3, $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED));

        // set statuses
        $this->set->setStatus('DoneTest1', ProcessSet::PROCESS_STATUS_DONE);
        $this->set->setStatus('DoneTest2', ProcessSet::PROCESS_STATUS_PREPARED); // first set prepared
        $this->set->setStatus('DoneTest2', ProcessSet::PROCESS_STATUS_DONE); // than set done
        $this->set->setStatus('PreparedTest', ProcessSet::PROCESS_STATUS_PREPARED);

        $doneProcesses = $this->set->get(ProcessSet::PROCESS_STATUS_DONE);
        $preparedProcesses = $this->set->get(ProcessSet::PROCESS_STATUS_PREPARED);

        // check both done processes
        $this->assertCount(2, $doneProcesses);
        $this->assertArrayHasKey('DoneTest1', $doneProcesses);
        $this->assertArrayHasKey('DoneTest2', $doneProcesses);

        // check one prepared process
        $this->assertCount(1, $preparedProcesses);
        $this->assertArrayHasKey('PreparedTest', $preparedProcesses);

        // no queued process left
        $this->assertCount(0, $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED));
    }

    /**
     * @dataProvider processResultProvider
     * @param int $exitCode
     * @param string $expectedResult
     */
    public function testShouldResolveAndStoreResultOfFinishedProcess($exitCode, $expectedResult)
    {
        $processMock = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $processMock->expects($this->once())
            ->method('getExitCode')
            ->willReturn($exitCode);

        $this->set->add($processMock, 'DoneTest');
        $this->set->setStatus('DoneTest', ProcessSet::PROCESS_STATUS_DONE);

        $doneProcesses = $this->set->get(ProcessSet::PROCESS_STATUS_DONE);

        $this->assertSame($expectedResult, $doneProcesses['DoneTest']->result);
    }

    /**
     * @return array[]
     */
    public function processResultProvider()
    {
        return [
            // $exitCode, $expectedResult
            'Testcase succeeded' => [\PHPUnit_TextUI_TestRunner::SUCCESS_EXIT, ProcessSet::PROCESS_RESULT_PASSED],
            'Exception thrown from PHPUnit' =>
                [\PHPUnit_TextUI_TestRunner::EXCEPTION_EXIT, ProcessSet::PROCESS_RESULT_FAILED],
            'Some test failed' =>
                [\PHPUnit_TextUI_TestRunner::FAILURE_EXIT, ProcessSet::PROCESS_RESULT_FAILED],
            'PHP fatal error' => [255, ProcessSet::PROCESS_RESULT_FATAL],
            'Process was killed' => [9, ProcessSet::PROCESS_RESULT_FATAL],
            'Process was terminated' => [9, ProcessSet::PROCESS_RESULT_FATAL],
            'Unrecognized exit error code should mark result as failed' => [66, ProcessSet::PROCESS_RESULT_FAILED],
        ];
    }

    public function testShouldNotStoreResultOfUnfinishedProcess()
    {
        $this->set->add(new Process(''), 'PreparedTest');
        $this->set->setStatus('PreparedTest', ProcessSet::PROCESS_STATUS_PREPARED);

        $preparedProcesses = $this->set->get(ProcessSet::PROCESS_STATUS_PREPARED);
        $this->assertNull($preparedProcesses['PreparedTest']->result);
    }

    public function testShouldPublishProcessStatusWhenStatusWasSet()
    {
        $publisherMock = $this->getMockBuilder(XmlPublisher::class)
            ->disableOriginalConstructor()
            ->getMock();

        $publisherMock->expects($this->at(1))
            ->method('publishResults')
            ->with(
                'FooClassName',
                ProcessSet::PROCESS_STATUS_DONE,
                ProcessSet::PROCESS_RESULT_PASSED,
                $this->identicalTo(null),
                $this->identicalTo(null)
            );

        $set = new ProcessSet($publisherMock);
        $set->add(new Process(''), 'FooClassName');
        $set->setStatus('FooClassName', ProcessSet::PROCESS_STATUS_DONE);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Process status must be one of "prepared, queued, done", but "WrongStatus" given
     */
    public function testFailIfWrongProcessStatusGiven()
    {
        $this->set->add(new Process(''), 'Foo');
        $this->set->setStatus('Foo', 'WrongStatus');
    }

    public function testShouldDequeueProcessesWithoutDelay()
    {
        $this->set->add(new Process(''), 'NoDelay'); // process without delay
        $this->set->add(new Process(''), 'Delayed', 'NoDelay', 3.3); // process with delay

        // Both processes should be queued after being added
        $processes = $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED);
        $this->assertCount(2, $processes);

        $outputBuffer = new BufferedOutput();
        // Dequeue process without delay
        $this->set->dequeueProcessesWithoutDelay($outputBuffer);

        // The process without delay should be prapared now
        $prepared = $this->set->get(ProcessSet::PROCESS_STATUS_PREPARED);
        $this->assertCount(1, $prepared);
        $this->assertArrayHasKey('NoDelay', $prepared);

        // The other process with delay should be kept as queued
        $queued = $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED);
        $this->assertCount(1, $queued);
        $this->assertArrayHasKey('Delayed', $queued);

        $output = $outputBuffer->fetch();
        $this->assertContains('Testcase "NoDelay" is prepared to be run', $output);
        $this->assertContains(
            'Testcase "Delayed" is queued to be run 3.3 minutes after testcase "NoDelay" is finished',
            $output
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Testcase "Foo" has @delayAfter dependency on "XXX", but this testcase was not defined.
     */
    public function testShouldFailBuildingTreeIfTestHasDependencyOnNotExistingTest()
    {
        $this->set->add(new Process(''), 'Foo', 'XXX', 5);
        $this->set->buildTree();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Cannot build tree graph from tests dependencies.
     */
    public function testShouldFailBuildingTreeIfCycleDetected()
    {
        //   ROOT
        //
        // A <--> B

        $this->set->add(new Process(''), 'A', 'B', 1);
        $this->set->add(new Process(''), 'B', 'A', 1);
        $this->set->buildTree();
    }

    public function testShouldBuildGraphTreeFromProcessDependencies()
    {
        //     ROOT
        //    /    \
        //   A      B
        //       3 / \ 5
        //        C   D

        // Order in which dependencies are added before buildTree() is called is not important,
        // so add leafs to the tree first.
        $this->set->add(new Process(''), 'C', 'B', 3);
        $this->set->add(new Process(''), 'D', 'B', 5);
        $this->set->add(new Process(''), 'A');
        $this->set->add(new Process(''), 'B');


        $tree = $this->set->buildTree();
        $this->assertInstanceOf(OutTree::class, $tree);

        $this->assertEquals(2, $tree->getHeight()); // 2, because single vertex graph has height 0
        $root = $tree->getVertexRoot();

        // Get vertices linked to root node (A, B)
        $verticesToRoot = $root->getVerticesEdgeTo();
        $this->assertCount(2, $verticesToRoot);
        $this->assertEquals(['A', 'B'], $verticesToRoot->getIds());
        // Default weight (if no dependency is specified) should be 0
        $this->assertSame(0, $verticesToRoot->getVertexId('A')->getEdgesFrom($root)->getEdgeFirst()->getWeight());

        // Vertex A should be a leaf but B should not
        $this->assertTrue($tree->isVertexLeaf($verticesToRoot->getVertexId('A')));
        $this->assertFalse($tree->isVertexLeaf($verticesToRoot->getVertexId('B')));

        // Get vertices linked to node B (C, D)
        $verticesToB = $tree->getVerticesChildren($verticesToRoot->getVertexId('B'));
        $this->assertCount(2, $verticesToB);
        $this->assertEquals(['C', 'D'], $verticesToB->getIds());
        $this->assertTrue($tree->isVertexLeaf($verticesToB->getVertexId('C')));
        $this->assertTrue($tree->isVertexLeaf($verticesToB->getVertexId('D')));

        // Check weights of edges to C (= 3) and D (= 5
        $this->assertEquals(3, $verticesToB->getVertexId('C')->getEdgesIn()->getEdgeFirst()->getWeight());
        $this->assertEquals(5, $verticesToB->getVertexId('D')->getEdgesIn()->getEdgeFirst()->getWeight());
    }

    public function testShouldChangeOrderOfProcessesByGivenStrategy()
    {
        //     ROOT
        //    /    \
        //   A      B
        //       3 / \ 5
        //        C   D

        $this->set->add(new Process(''), 'A');
        $this->set->add(new Process(''), 'B');
        $this->set->add(new Process(''), 'C', 'B', 3);
        $this->set->add(new Process(''), 'D', 'B', 5);

        // The MockOrderStrategy return processes with order values A - 0, B - 1, C - 2, D - 3.
        // Thus after optimization the processes in processSet should be sorted descending
        $processesBefore = $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED);
        $this->assertSame(['A', 'B', 'C', 'D'], array_keys($processesBefore));

        $this->set->optimizeOrder(new MockOrderStrategy());

        $processesAfter = $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED);
        $this->assertSame(['D', 'C', 'B', 'A'], array_keys($processesAfter));
    }
}
