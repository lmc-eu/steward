<?php

namespace Lmc\Steward\Process;

use Assert\InvalidArgumentException;
use Graphp\Algorithms\Tree\OutTree;
use Lmc\Steward\Process\Fixtures\MockOrderStrategy;
use Lmc\Steward\Publisher\XmlPublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Process\Process;

class ProcessSetTest extends TestCase
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
        $this->set->add(new ProcessWrapper(new Process(''), 'Foo'));
        $this->assertCount(1, $this->set);
        $this->set->add(new ProcessWrapper(new Process(''), 'Bar'));
        $this->set->add(new ProcessWrapper(new Process(''), 'Baz'));
        $this->assertCount(3, $this->set);
    }

    public function testShouldFailWhenAddingTestWithNonUniqueName()
    {
        $this->set->add(new ProcessWrapper(new Process(''), 'Foo\Bar'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Testcase with name "Foo\Bar" was already added');

        $this->set->add(new ProcessWrapper(new Process(''), 'Foo\Bar'));
    }

    public function testShouldHasNewlyAddedProcessInQueuedState()
    {
        $this->set->add(new ProcessWrapper(new Process(''), 'Foo'));
        $this->set->add(new ProcessWrapper(new Process(''), 'Bar'));

        $this->assertCount(2, $this->set->get(ProcessWrapper::PROCESS_STATUS_QUEUED));
        $this->assertCount(0, $this->set->get(ProcessWrapper::PROCESS_STATUS_DONE));
        $this->assertCount(0, $this->set->get(ProcessWrapper::PROCESS_STATUS_PREPARED));
    }

    public function testShouldAddAndGetWrappedProcesses()
    {
        $processFoo = new ProcessWrapper(new Process(''), 'Foo');
        $processBaz = new ProcessWrapper(new Process(''), 'Baz');
        $this->set->add($processFoo);
        $this->set->add($processBaz);

        $processes = $this->set->get(ProcessWrapper::PROCESS_STATUS_QUEUED);

        $this->assertSame($processBaz, $processes['Baz']);
        $this->assertSame($processFoo, $processes['Foo']);
    }

    public function testShouldRetrieveProcessesByStatus()
    {
        $doneTest1 = new ProcessWrapper(new Process(''), 'DoneTest1');
        $doneTest2 = new ProcessWrapper(new Process(''), 'DoneTest2');
        $preparedTest = new ProcessWrapper(new Process(''), 'PreparedTest');
        $this->set->add($doneTest1);
        $this->set->add($doneTest2);
        $this->set->add($preparedTest);

        // all processes are queued by default
        $this->assertCount(3, $this->set->get(ProcessWrapper::PROCESS_STATUS_QUEUED));

        // set statuses
        $doneTest1->setStatus(ProcessWrapper::PROCESS_STATUS_DONE);
        $doneTest2->setStatus(ProcessWrapper::PROCESS_STATUS_DONE);
        $preparedTest->setStatus(ProcessWrapper::PROCESS_STATUS_PREPARED);

        // retrieve processes by status
        $doneProcesses = $this->set->get(ProcessWrapper::PROCESS_STATUS_DONE);
        $preparedProcesses = $this->set->get(ProcessWrapper::PROCESS_STATUS_PREPARED);

        // check both done processes
        $this->assertCount(2, $doneProcesses);
        $this->assertSame($doneTest1, $doneProcesses['DoneTest1']);
        $this->assertSame($doneTest2, $doneProcesses['DoneTest2']);

        // check one prepared process
        $this->assertCount(1, $preparedProcesses);
        $this->assertSame($preparedTest, $preparedProcesses['PreparedTest']);

        // no queued process left
        $this->assertCount(0, $this->set->get(ProcessWrapper::PROCESS_STATUS_QUEUED));
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
                ProcessWrapper::PROCESS_STATUS_QUEUED,
                $this->identicalTo(null),
                $this->identicalTo(null),
                $this->identicalTo(null)
            );

        $set = new ProcessSet($publisherMock);
        $set->add(new ProcessWrapper(new Process(''), 'FooClassName'));
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
                ProcessWrapper::PROCESS_STATUS_QUEUED,
                $this->identicalTo(null),
                $this->identicalTo(null),
                $this->identicalTo(null)
            );

        $set->setPublisher($publisherMock);
        $set->add(new ProcessWrapper(new Process(''), 'FooClassName'));
    }

    public function testShouldCountStatusesOfWrappedProcesses()
    {
        $doneTest1 = new ProcessWrapper(new Process(''), 'DoneTest1');
        $doneTest1->setStatus(ProcessWrapper::PROCESS_STATUS_DONE);
        $doneTest2 = new ProcessWrapper(new Process(''), 'DoneTest2');
        $doneTest2->setStatus(ProcessWrapper::PROCESS_STATUS_DONE);
        $queuedTest = new ProcessWrapper(new Process(''), 'QueuedTest');
        $queuedTest->setStatus(ProcessWrapper::PROCESS_STATUS_QUEUED);
        $preparedTest = new ProcessWrapper(new Process(''), 'PreparedTest');
        $preparedTest->setStatus(ProcessWrapper::PROCESS_STATUS_PREPARED);

        $this->set->add($doneTest1);
        $this->set->add($queuedTest);
        $this->set->add($preparedTest);
        $this->set->add($doneTest2);

        $this->assertEquals(
            [
                ProcessWrapper::PROCESS_STATUS_PREPARED => 1,
                ProcessWrapper::PROCESS_STATUS_QUEUED => 1,
                ProcessWrapper::PROCESS_STATUS_DONE => 2,
            ],
            $this->set->countStatuses()
        );
    }

    public function testShouldCountResultsOfDoneProcesses()
    {
        $processWrapperMock = $this->getMockBuilder(ProcessWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $processWrapperMock->expects($this->exactly(4))
            ->method('getClassName')
            ->willReturnOnConsecutiveCalls('Process1', 'Process2', 'Process3', 'Process4');
        $processWrapperMock->expects($this->exactly(4))
            ->method('getStatus')
            ->willReturn(ProcessWrapper::PROCESS_STATUS_DONE);

        $processWrapperMock->expects($this->exactly(4))
            ->method('getResult')
            ->willReturnOnConsecutiveCalls(
                ProcessWrapper::PROCESS_RESULT_PASSED,
                ProcessWrapper::PROCESS_RESULT_FAILED,
                ProcessWrapper::PROCESS_RESULT_FATAL,
                ProcessWrapper::PROCESS_RESULT_PASSED
            );

        $this->set->add($processWrapperMock);
        $this->set->add($processWrapperMock);
        $this->set->add($processWrapperMock);
        $this->set->add($processWrapperMock);

        $this->assertEquals(
            [
                ProcessWrapper::PROCESS_RESULT_PASSED => 2,
                ProcessWrapper::PROCESS_RESULT_FAILED => 1,
                ProcessWrapper::PROCESS_RESULT_FATAL => 1,
            ],
            $this->set->countResults()
        );
    }

    public function testShouldDequeueProcessesWithoutDelay()
    {
        $noDelayTest = new ProcessWrapper(new Process(''), 'NoDelay');
        $delayedTest = new ProcessWrapper(new Process(''), 'Delayed');
        $delayedTest->setDelay('NoDelay', 3.3);
        $this->set->add($noDelayTest);
        $this->set->add($delayedTest);
        $outputBuffer = new BufferedOutput(Output::VERBOSITY_DEBUG);

        // Preconditions - both processes should be queued after being added
        $processes = $this->set->get(ProcessWrapper::PROCESS_STATUS_QUEUED);
        $this->assertCount(2, $processes);

        // Should Dequeue process without delay
        $this->set->dequeueProcessesWithoutDelay($outputBuffer);

        // The process without delay should be prepared now
        $prepared = $this->set->get(ProcessWrapper::PROCESS_STATUS_PREPARED);
        $this->assertCount(1, $prepared);
        $this->assertSame($noDelayTest, $prepared['NoDelay']);

        // The other process with delay should be kept as queued
        $queued = $this->set->get(ProcessWrapper::PROCESS_STATUS_QUEUED);
        $this->assertCount(1, $queued);
        $this->assertSame($delayedTest, $queued['Delayed']);

        $output = $outputBuffer->fetch();
        $this->assertContains('Testcase "NoDelay" is prepared to be run', $output);
        $this->assertContains(
            'Testcase "Delayed" is queued to be run 3.3 minutes after testcase "NoDelay" is finished',
            $output
        );
    }

    public function testShouldFailBuildingTreeIfTestHasDependencyOnNotExistingTest()
    {
        $process = new ProcessWrapper(new Process(''), 'Foo');
        $process->setDelay('NotExisting', 5);

        $this->set->add($process);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Testcase "Foo" has @delayAfter dependency on "NotExisting", but this testcase was not defined.'
        );
        $this->set->buildTree();
    }

    public function testShouldFailBuildingTreeIfCycleDetected()
    {
        /*
            ROOT

          A <--> B
        */

        $processA = new ProcessWrapper(new Process(''), 'A');
        $processA->setDelay('B', 1);
        $processB = new ProcessWrapper(new Process(''), 'B');
        $processB->setDelay('A', 1);

        $this->set->add($processA);
        $this->set->add($processB);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot build tree graph from tests dependencies.');

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
        $processC = new ProcessWrapper(new Process(''), 'C');
        $processC->setDelay('B', 3);
        $processD = new ProcessWrapper(new Process(''), 'D');
        $processD->setDelay('B', 5);
        $processA = new ProcessWrapper(new Process(''), 'A');
        $processB = new ProcessWrapper(new Process(''), 'B');

        $this->set->add($processC);
        $this->set->add($processD);
        $this->set->add($processA);
        $this->set->add($processB);

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

        $processA = new ProcessWrapper(new Process(''), 'A');
        $processB = new ProcessWrapper(new Process(''), 'B');
        $processC = new ProcessWrapper(new Process(''), 'C');
        $processC->setDelay('B', 3);
        $processD = new ProcessWrapper(new Process(''), 'D');
        $processD->setDelay('B', 5);

        $this->set->add($processA);
        $this->set->add($processB);
        $this->set->add($processC);
        $this->set->add($processD);

        // Check original order, not yet affected by order strategy
        $processesBefore = $this->set->get(ProcessWrapper::PROCESS_STATUS_QUEUED);
        $this->assertSame(['A', 'B', 'C', 'D'], array_keys($processesBefore));

        // The MockOrderStrategy return processes with order values A - 0, B - 1, C - 2, D - 3.
        // Thus after optimization the processes in processSet should be sorted descending from the highest number
        $this->set->optimizeOrder(new MockOrderStrategy());
        $processesAfter = $this->set->get(ProcessWrapper::PROCESS_STATUS_QUEUED);
        $this->assertSame(['D', 'C', 'B', 'A'], array_keys($processesAfter));
    }

    public function testShouldFailDependantsOfGivenProcess()
    {
        //     ROOT
        //    /    \
        //   A      B
        //       3 / \ 5
        //        C   D
        //            | 0 (zero delay is also allowed)
        //            E

        $processA = new ProcessWrapper(new Process(''), 'A');
        $processB = new ProcessWrapper(new Process(''), 'B');
        $processC = new ProcessWrapper(new Process(''), 'C');
        $processC->setDelay('B', 3);
        $processD = new ProcessWrapper(new Process(''), 'D');
        $processD->setDelay('B', 5);
        $processE = new ProcessWrapper(new Process(''), 'E');
        $processE->setDelay('D', 0);

        $this->set->add($processA);
        $this->set->add($processB);
        $this->set->add($processC);
        $this->set->add($processD);
        $this->set->add($processE);

        $this->set->buildTree();

        $processB->setStatus(ProcessWrapper::PROCESS_STATUS_DONE);

        // Test preconditions - one process is done, other are queued
        $this->assertCount(1, $this->set->get(ProcessWrapper::PROCESS_STATUS_DONE));
        $this->assertCount(4, $this->set->get(ProcessWrapper::PROCESS_STATUS_QUEUED));
        $this->assertEquals(1, $this->set->countResults()[ProcessWrapper::PROCESS_RESULT_FAILED]);

        $failedProcesses = $this->set->failDependants('B');
        $this->assertCount(3, $failedProcesses);
        $this->assertContainsOnlyInstancesOf(ProcessWrapper::class, $failedProcesses);
        $this->assertArrayHasKey('C', $failedProcesses);
        $this->assertArrayHasKey('D', $failedProcesses);
        $this->assertArrayHasKey('E', $failedProcesses);

        // A will still be queued
        $this->assertCount(1, $this->set->get(ProcessWrapper::PROCESS_STATUS_QUEUED));
        $this->assertSame(ProcessWrapper::PROCESS_STATUS_QUEUED, $processA->getStatus());
        // C, D and E processes will be done and failed
        $this->assertCount(4, $this->set->get(ProcessWrapper::PROCESS_STATUS_DONE));
        $this->assertSame(ProcessWrapper::PROCESS_STATUS_DONE, $processC->getStatus());
        $this->assertSame(ProcessWrapper::PROCESS_STATUS_DONE, $processD->getStatus());
        $this->assertSame(ProcessWrapper::PROCESS_STATUS_DONE, $processE->getStatus());
        $this->assertEquals(4, $this->set->countResults()[ProcessWrapper::PROCESS_RESULT_FAILED]);
        $this->assertSame(ProcessWrapper::PROCESS_RESULT_FAILED, $processC->getResult());
        $this->assertSame(ProcessWrapper::PROCESS_RESULT_FAILED, $processD->getResult());
        $this->assertSame(ProcessWrapper::PROCESS_RESULT_FAILED, $processE->getResult());
    }

    public function testShouldFailWhenFailingDependantsButTheTreeWasNotYetBuilt()
    {
        $processA = new ProcessWrapper(new Process(''), 'A');
        $processB = new ProcessWrapper(new Process(''), 'B');
        $processB->setDelay('A', 3);

        $this->set->add($processA);
        $this->set->add($processB);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot get dependency tree - the tree was not yet build using buildTree()');

        $this->set->failDependants('A');
    }
}
