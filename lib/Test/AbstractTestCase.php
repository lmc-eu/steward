<?php

namespace Lmc\Steward\Test;

use Lmc\Steward\Component\TestUtils;

/**
 * Abstract test case to be used by all test cases.
 * It adds logging, some common logic and assertions.
 *
 * @copyright LMC s.r.o.
 */
abstract class AbstractTestCase extends AbstractTestCaseBase
{
    /** @var int Width of browser window */
    public static $browserWidth = 1280;

    /** @var int Height of browser window */
    public static $browserHeight = 1024;

    /** @var TestUtils Common test utils, instantiated on setUp. */
    public $utils;

    /** @var string Log appended to output of this test */
    protected $appendedTestLog;

    /**
     * Names of existing LMC environments
     * @todo TODO: remove - is LMC specific
     * @var array
     */
    public static $lmcEnvs = ['dev1', 'dev2', 'dev3', 'devel', 'deploy', 'prod'];

    public function setUp()
    {
        $this->log('Starting execution of test ' . get_called_class() . '::' . $this->getName());

        $this->wd->manage()->window()->setSize(new \WebDriverDimension(static::$browserWidth, static::$browserHeight));

        $this->utils = new TestUtils($this);
    }

    public function tearDown()
    {
        $this->log('Finished execution of test ' . get_called_class() . '::' . $this->getName());
    }

    /**
     * Get output of current test. Parent method is overwritten to include also $appendedTestLog in the output
     * (called eg. from \PHPUnit_Util_Log_JUnit).
     * @return string
     */
    public function getActualOutput()
    {
        $output = parent::getActualOutput();
        $output .= $this->appendedTestLog;

        return $output;
    }

    /**
     * Append given output at the end of test's log. This is useful especially when called from
     * Listeners, as the standard output won't be part of test output buffer.
     * @param $format
     * @param $args
     * @see log
     */
    public function appendTestLog($format, $args = null)
    {
        $output = $this->formatOutput(func_get_args());
        $this->appendedTestLog .= $output;
    }

    /**
     * Prepend artifact's URL to given relative pat to make it clickable in Jenkins output.
     * Won't affect output outside Jenkins.
     * @todo TODO: remove - is LMC specific
     * @param $path
     * @return string
     */
    public function prependArtifactUrl($path)
    {
        if (getenv('JENKINS_URL') && getenv('BUILD_URL') && getenv('WORKSPACE')) {
            $realPath = realpath($path);
            if ($realPath) {
                // from absolute path, remove workspace
                $path = str_replace(getenv('WORKSPACE'), '', $realPath);
                // prepend url to artifact
                $path = getenv('BUILD_URL') . "artifact/" . $path;
            }
        }
        return $path;
    }

    public function log($format, $args = null)
    {
        echo $this->formatOutput(func_get_args());
    }

    public function warn($format, $args = null)
    {
        echo $this->formatOutput(func_get_args(), $isWarn = true);
    }

    /**
     * Format output
     * @param array $args Array of arguments passed to original sprintf()-like function
     * @param bool $isWarn OPTIONAL Is message warning? Default is false.
     * @return string Formatted output
     */
    protected function formatOutput(array $args, $isWarn = false)
    {
        $format = array_shift($args);

        // If first item of arguments contains another array use it as arguments
        if (!empty($args) && is_array($args[0])) {
            $args = $args[0];
        }

        return '[' . date("Y-m-d H:i:s") . ']'
        . ($isWarn ? ' [WARN]' : '')
        . ': '
        . vsprintf($format, $args)
        . "\n";
    }
}
