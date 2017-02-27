<?php

namespace Lmc\Steward\Process;

use Fhaculty\Graph\Graph;
use Graphp\Algorithms\Tree\OutTree;
use PHPUnit\Framework\TestCase;

class MaxTotalDelayStrategyTest extends TestCase
{
    public function testShouldOptimizeOrderBasedOnMaxSubtreeDelay()
    {
        //     -------ROOT-------
        //    / 0   / 0    \ 0   \ 0
        //   A      B      F     H
        //       3 / \ 5   | 11  | 4
        //        C   D    G     I
        //     10 |
        //        E

        // Build graph
        $graph = new Graph();
        $root = $graph->createVertex(0);

        $root->createEdgeTo($graph->createVertex('A'))->setWeight(0);

        $root->createEdgeTo($vertexB = $graph->createVertex('B'))->setWeight(0);
        $vertexB->createEdgeTo($vertexC = $graph->createVertex('C'))->setWeight(3);
        $vertexB->createEdgeTo($graph->createVertex('D'))->setWeight(5);
        $vertexC->createEdgeTo($graph->createVertex('E'))->setWeight(10);

        $root->createEdgeTo($vertexF = $graph->createVertex('F'))->setWeight(0);
        $vertexF->createEdgeTo($graph->createVertex('G'))->setWeight(11);

        $root->createEdgeTo($vertexH = $graph->createVertex('H'))->setWeight(0);
        $vertexH->createEdgeTo($graph->createVertex('I'))->setWeight(4);

        // Check the vertices have proper order value
        $strategy = new MaxTotalDelayStrategy();
        $evaluatedOrder = $strategy->optimize(new OutTree($graph));

        $this->assertSame($evaluatedOrder['A'], 0);
        $this->assertSame($evaluatedOrder['B'], 13);
        $this->assertSame($evaluatedOrder['C'], 10);
        $this->assertSame($evaluatedOrder['D'], 0);
        $this->assertSame($evaluatedOrder['E'], 0);
        $this->assertSame($evaluatedOrder['F'], 11);
        $this->assertSame($evaluatedOrder['G'], 0);
        $this->assertSame($evaluatedOrder['H'], 4);
        $this->assertSame($evaluatedOrder['I'], 0);
    }
}
