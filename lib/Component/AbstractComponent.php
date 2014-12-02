<?php

namespace Lmc\Steward\Component;

use Lmc\Steward\Test\AbstractTestCaseBase;

/**
 * AbstractComponent used as parent of all components to add some default functionality and interface .
 *
 * @method void log(string $message, $arguments = null)
 * @method void warn(string $message, $arguments = null)
 */
abstract class AbstractComponent
{
    /** @var AbstractTestCaseBase */
    protected $tc;

    /** @var \WebDriver */
    protected $wd;

    /** @var string */
    protected $componentName;

    /**
     * @param AbstractTestCaseBase $tc TestCase instance
     */
    public function __construct(AbstractTestCaseBase $tc)
    {
        $this->tc = $tc;
        $this->wd = $tc->wd;

        $reflection = new \ReflectionClass($this);
        $this->componentName = $reflection->getShortName();
    }

    public function __call($name, $arguments)
    {
        // Methods log() and warn() prepend componentName to message and call the same method on TestCase.
        if ($name == 'log' || $name == 'warn') {
            $arguments[0] = '[' . $this->componentName . '] ' . $arguments[0];
            return call_user_func_array([$this->tc, $name], $arguments);
        }

        trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
    }
}
