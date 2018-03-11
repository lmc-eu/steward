<?php declare(strict_types=1);

namespace Lmc\Steward\Process;

use Graphp\Algorithms\Tree\OutTree;

/**
 * Interface for optimizers of tests order.
 */
interface OptimizeOrderInterface
{
    /**
     * For each vertex in the tree (except root node) evaluate its order.
     * This determines the order in which tests (that have same time delay or no time delay) would be stared.
     *
     * @return array Array of [string key (= testclass fully qualified name) => int value (= test order)]
     */
    public function optimize(OutTree $tree): array;
}
