<?php declare(strict_types=1);

namespace Lmc\Steward\Process;

use Assert\Assertion;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use Graphp\Algorithms\Tree\OutTree;
use Lmc\Steward\Exception\LogicException;
use Lmc\Steward\Exception\RuntimeException;
use Lmc\Steward\Publisher\AbstractPublisher;

/**
 * Set of Test processes.
 */
class ProcessSet implements \Countable
{
    /**
     * Array of wrapped Processes, indexed by testcase fully qualified name
     *
     * @var ProcessWrapper[]
     */
    protected $processes = [];
    /** @var AbstractPublisher|null */
    protected $publisher;
    /** @var Graph */
    protected $graph;
    /** @var OutTree */
    protected $tree;

    /**
     * Instantiate processSet to manage processes in different states,
     * If publisher is passed, it is used to publish process statuses after status changes.
     */
    public function __construct(AbstractPublisher $publisher = null)
    {
        $this->publisher = $publisher;

        $this->graph = new Graph();
    }

    public function setPublisher(AbstractPublisher $publisher): void
    {
        $this->publisher = $publisher;
    }

    /**
     * Get count of all processes in the set
     */
    public function count(): int
    {
        return count($this->processes);
    }

    /**
     * Add new process to the set.
     */
    public function add(ProcessWrapper $processWrapper): void
    {
        $className = $processWrapper->getClassName();
        if (isset($this->processes[$className])) {
            throw RuntimeException::forDuplicateClassName($className);
        }

        $this->processes[$className] = $processWrapper;

        $this->graph->createVertex($className);

        if ($this->publisher) {
            $this->publisher->publishResults($className, ProcessWrapper::PROCESS_STATUS_QUEUED, null);
        }
    }

    /**
     * Get array of processes in the set having given status
     *
     * @return ProcessWrapper[]
     */
    public function get(string $status): array
    {
        Assertion::choice($status, ProcessWrapper::PROCESS_STATUSES);

        $return = [];
        foreach ($this->processes as $className => $processWrapper) {
            if ($processWrapper->getStatus() === $status) {
                $return[$className] = $processWrapper;
            }
        }

        return $return;
    }

    /**
     * Build out-tree graph from defined Processes and their relations.
     *
     * @internal Should be called directly only in unit-testing
     */
    public function buildTree(): OutTree
    {
        if ($this->tree === null) {
            $root = $this->graph->createVertex(0);

            // Create edges directed from the root node
            foreach ($this->processes as $className => $processWrapper) {
                $vertex = $this->graph->getVertex($className);

                if ($processWrapper->getDelayMinutes() === null) { // doesn't depend on anything => link it to the root
                    $root->createEdgeTo($vertex)->setWeight(0);
                } else { // is dependant => link it to its dependency
                    // Throw error if dependency is to not existing vertex
                    if (!$this->graph->hasVertex($processWrapper->getDelayAfter())) {
                        throw LogicException::forDelayWithNotExistingTestcase(
                            $className,
                            $processWrapper->getDelayAfter()
                        );
                    }

                    $this->graph->getVertex($processWrapper->getDelayAfter())
                        ->createEdgeTo($vertex)
                        ->setWeight($processWrapper->getDelayMinutes());
                }
            }
        }

        $this->tree = new OutTree($this->graph);

        if (!$this->tree->isTree()) {
            throw LogicException::forCyclicDependencyInGraph();
        }

        return $this->tree;
    }

    /**
     * Optimize order of processes using given strategy.
     */
    public function optimizeOrder(OptimizeOrderInterface $optimizeStrategy): void
    {
        $optimizedOrder = $optimizeStrategy->optimize($this->buildTree());

        // Sort the $optimizedOrder array to have the same order as corresponding array of processes
        // (so that the array could be passed to array_multisort())
        $sortingArray = [];
        foreach ($this->processes as $processClassName => $processObject) {
            $sortingArray[$processClassName] = $optimizedOrder[$processClassName];
        }

        // Sort processes descending according to corresponding values in $sortingArray
        array_multisort($sortingArray, SORT_DESC, SORT_NUMERIC, $this->processes);
    }

    /**
     * Get count of processes status
     */
    public function countStatuses(): array
    {
        $statusesCount = [];
        foreach (ProcessWrapper::PROCESS_STATUSES as $status) {
            $statusesCount[$status] = count($this->get($status));
        }

        return $statusesCount;
    }

    /**
     * Get result counts of done processes
     */
    public function countResults(): array
    {
        $done = $this->get(ProcessWrapper::PROCESS_STATUS_DONE);
        $resultsCount = [
            ProcessWrapper::PROCESS_RESULT_PASSED => 0,
            ProcessWrapper::PROCESS_RESULT_FAILED => 0,
            ProcessWrapper::PROCESS_RESULT_FATAL => 0,
        ];

        // Retrieve names of done processes and count their results
        foreach ($done as $processObject) {
            $resultsCount[$processObject->getResult()]++;
        }

        return $resultsCount;
    }

    /**
     * Mark all dependant processes of given process as failed
     *
     * @return ProcessWrapper[] Processes that has been failed
     */
    public function failDependants(string $className): array
    {
        $descendantProcesses = $this->getDependencyTree($className);

        $failedProcesses = [];
        foreach ($descendantProcesses as $processClassName => $processWrapper) {
            $failedProcesses[$processClassName] = $processWrapper;
            $processWrapper->setStatus(ProcessWrapper::PROCESS_STATUS_DONE);
        }

        return $failedProcesses;
    }

    /**
     * Get all wrapped processes that depends on process of given name.
     *
     * @return ProcessWrapper[]
     */
    protected function getDependencyTree(string $className): array
    {
        Assertion::notEmpty($this->tree, 'Cannot get dependency tree - the tree was not yet build using buildTree()');

        $descendants = $this->tree->getVerticesDescendant($this->graph->getVertex($className));

        /** @var ProcessWrapper[] $descendantProcesses */
        $descendantProcesses = [];

        /** @var Vertex $descendant */
        foreach ($descendants as $descendant) {
            $descendantProcesses[$descendant->getId()] = $this->processes[$descendant->getId()];
        }

        return $descendantProcesses;
    }
}
