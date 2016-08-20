<?php

namespace Lmc\Steward\Component;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\Test\AbstractTestCaseBase;
use Lmc\Steward\Test\SyntaxSugarTrait;

/**
 * AbstractComponent used as parent of all components to add some default functionality and interface .
 *
 * @method void log(string $format, ...$args)
 * @method void warn(string $format, ...$args)
 * @method void debug(string $format, ...$args)
 */
abstract class AbstractComponent
{
    use SyntaxSugarTrait;

    /** @var AbstractTestCaseBase */
    protected $tc;

    /** @var RemoteWebDriver */
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
        // Log methods prepend componentName to message and call the same method on TestCase.
        if (in_array($name, ['log', 'warn', 'debug'])) {
            $arguments[0] = '[' . $this->componentName . '] ' . $arguments[0];

            return call_user_func_array([$this->tc, $name], $arguments);
        }

        trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
    }
}
