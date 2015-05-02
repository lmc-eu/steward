<?php

namespace Lmc\Steward\Process;

use Lmc\Steward\Process\Fixtures\DummyPublisher;
use Lmc\Steward\Process\Fixtures\MockOrderStrategy;
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
        $this->set->add(new Process(''), 'Bar');
        $this->set->add(new Process(''), 'Foo', 'Bar');
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
