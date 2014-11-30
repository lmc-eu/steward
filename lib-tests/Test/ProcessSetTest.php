<?php

namespace Lmc\Steward\Test;

use Lmc\Steward\Test\Fixtures\DummyPublisher;
use Symfony\Component\Process\Process;

class ProcessSetTest extends \PHPUnit_Framework_TestCase
{
    /** @var ProcessSet */
    protected $set;

    public function setUp()
    {
        $publisher = new DummyPublisher(null, null, null);
        $this->set = new ProcessSet($publisher);
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
        $this->set->add(new Process(''), 'Foo', 'Bar');
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

    public function testShouldAddAndGetProcess()
    {
        $this->set->add(new Process(''), 'Foo');
        $this->set->add(new Process(''), 'Baz', 'Foo', 5);

        $processes = $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED);
        $this->assertArrayHasKey('Foo', $processes);
        $this->assertArrayHasKey('Baz', $processes);

        $process = $processes['Baz'];
        $this->assertInstanceOf('stdClass', $process);
        $this->assertEquals(ProcessSet::PROCESS_STATUS_QUEUED, $process->status);
        $this->assertInstanceOf('Symfony\Component\Process\Process', $process->process);
        $this->assertEquals('Foo', $process->delayAfter);
        $this->assertSame(5, $process->delayMinutes);
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
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Process status must be one of "prepared, queued, done", but "WrongStatus" given
     */
    public function testFailIfWrongProcessStatusGiven()
    {
        $this->set->add(new Process(''), 'Foo');
        $this->set->setStatus('Foo', 'WrongStatus');
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
        $this->assertInstanceOf('Fhaculty\Graph\Algorithm\Tree\OutTree', $tree);

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
        $this->assertSame(3, $verticesToB->getVertexId('C')->getEdgesIn()->getEdgeFirst()->getWeight());
        $this->assertSame(5, $verticesToB->getVertexId('D')->getEdgesIn()->getEdgeFirst()->getWeight());
    }

    public function testShouldOptimizeOrder()
    {
        //     -------ROOT-------
        //    /     /      \     \
        //   A      B      F     H
        //       3 / \ 5   | 11  | 4
        //        C   D    G     I
        //     10 |
        //        E

        // The proper order should be:
        // 1. B (because the longest dependency has 13 minutes)
        // 2. F (11 minutes)
        // 3. C (10 minutes, but from the moment it will be unqueued)
        // 4. H (4 minutes)
        // 5.-9. A, E, D, G, I (nothing depends on them)

        $this->set->add(new Process(''), 'A');

        $this->set->add(new Process(''), 'B');
        $this->set->add(new Process(''), 'C', 'B', 3);
        $this->set->add(new Process(''), 'D', 'B', 5);
        $this->set->add(new Process(''), 'E', 'C', 10);

        $this->set->add(new Process(''), 'F');
        $this->set->add(new Process(''), 'G', 'F', 11);

        $this->set->add(new Process(''), 'H');
        $this->set->add(new Process(''), 'I', 'H', 4);


        $processesBefore = $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED);
        $this->assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'], array_keys($processesBefore));

        $this->set->optimizeOrder();

        $processesAfter = $this->set->get(ProcessSet::PROCESS_STATUS_QUEUED);
        $orderAfter = array_keys($processesAfter);
        $this->assertEquals('B', $orderAfter[0]);
        $this->assertEquals('F', $orderAfter[1]);
        $this->assertEquals('C', $orderAfter[2]);
        $this->assertEquals('H', $orderAfter[3]);

        // Order of other test is not stable
        $remainingTests = [$orderAfter[4], $orderAfter[5], $orderAfter[6], $orderAfter[7], $orderAfter[8]];
        $this->assertContains('A', $remainingTests);
        $this->assertContains('E', $remainingTests);
        $this->assertContains('D', $remainingTests);
        $this->assertContains('G', $remainingTests);
        $this->assertContains('I', $remainingTests);
    }
}
