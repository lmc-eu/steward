<?php

namespace Lmc\Steward\Component;

abstract class AbstractComponent
{
    /** @var \Lmc\Steward\Test\AbstractTestCaseBase */
    protected $tc;

    /** @var string */
    protected $componentName;

    /**
     * @param \Lmc\Steward\Test\AbstractTestCaseBase $tc TestCase instance
     */
    public function __construct(\Lmc\Steward\Test\AbstractTestCaseBase $tc)
    {
        $this->tc = $tc;

        $reflection = new \ReflectionClass($this);
        $this->componentName = $reflection->getShortName();
    }

    public function __call($name, $arguments)
    {
        // Methods log() and warn() prepend componentName to message and call the same method on TestCase.
        if ($name == 'log' || $name == 'warn') {
            $arguments[0] = '[' . $this->componentName . '] ' . $arguments[0];
            call_user_method_array($name, $this->tc, $arguments);
        }
    }
}
