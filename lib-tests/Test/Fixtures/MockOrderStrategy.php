<?php

namespace Lmc\Steward\Test\Fixtures;

use Fhaculty\Graph\Algorithm\Tree\OutTree;
use Lmc\Steward\Test\OptimizeOrderInterface;

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
        foreach ($vertices as $vertex) {
            $output[$vertex->getId()] = $i++;
        }

        return $output;
    }
}
