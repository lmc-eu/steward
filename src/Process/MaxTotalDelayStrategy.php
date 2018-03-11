<?php declare(strict_types=1);

namespace Lmc\Steward\Process;

use Fhaculty\Graph\Vertex;
use Graphp\Algorithms\ShortestPath\Dijkstra;
use Graphp\Algorithms\Tree\OutTree;

class MaxTotalDelayStrategy implements OptimizeOrderInterface
{
    /**
     * Optimize order of tests in given tree based on their defined delay.
     * The aim is to run as first processes having the longest delay of their sub-dependencies.
     *
     * @return array Array of [string key (= testclass fully qualified name) => int value (= test order)]
     */
    public function optimize(OutTree $tree): array
    {
        // get root node of the tree
        $root = $tree->getVertexRoot();

        // get all root descendants vertices (without the root vertex itself)
        $children = $tree->getVerticesDescendant($root);

        // for each vertex (process) get maximum total weight of its subtree (longest distance)
        $subTreeMaxDistances = [];
        /** @var Vertex $childVertex */
        foreach ($children as $childVertex) {
            $alg = new Dijkstra($childVertex);
            // get map with distances to all linked reachable vertexes
            $distanceMap = $alg->getDistanceMap();
            // save the longest distance
            $subTreeMaxDistances[$childVertex->getId()] = $distanceMap ? max($distanceMap) : 0;
        }

        return $subTreeMaxDistances;
    }
}
