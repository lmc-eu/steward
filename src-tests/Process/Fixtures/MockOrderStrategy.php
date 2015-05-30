<?php

namespace Lmc\Steward\Process\Fixtures;

use Fhaculty\Graph\Vertex;
use Graphp\Algorithms\Tree\OutTree;
use Lmc\Steward\Process\OptimizeOrderInterface;

class MockOrderStrategy implements OptimizeOrderInterface
{
    /**
     * Dummy strategy to return as order value index of the vertex
     *
     * @param OutTree $tree
     * @return array
     */
    public function optimize(OutTree $tree)
    {
        $vertices = $tree->getVerticesDescendant($tree->getVertexRoot());

        $i = 0;
        $output = [];
        /** @var Vertex $vertex */
        foreach ($vertices as $vertex) {
            $output[$vertex->getId()] = $i++;
        }

        return $output;
    }
}
